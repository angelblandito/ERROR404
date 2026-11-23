/* =====================================================================
   JAM - Base de datos distribuida
   06. Pedidos, tipos de cuenta y usuario de la API
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql y 01_fragmentacion_horizontal.sql ejecutados.

   dbo.sp_crear_pedido
     Registra un pedido completo como una sola transacción:
       - valida sucursal, cliente, empleado, cupón y artículos;
       - toma los precios del catálogo (nunca del cliente);
       - descuenta el inventario de la sucursal solo si alcanza el stock;
       - inserta customer_order, order_item y payment;
       - registra el domicilio (envío) y el pago simulado;
       - aplica los beneficios del tipo de cuenta (account_tier): descuento,
         envío gratis y prioritario, y piezas permitidas por artículo;
       - cobra los productos de outlet con su descuento fijo
         (product.outlet_percent); a esos no se les suma cupón ni descuento
         de la cuenta;
       - registra el empaque de regalo ($30): ocasión, color del papel y, si
         se quiere, para quién es y una dedicatoria (tabla order_gift).
     Si cualquier paso falla no queda nada a medias (XACT_ABORT + TRY/CATCH).
     Errores de negocio: THROW 50001-50019, la API los devuelve como HTTP 409.

   dbo.sp_evaluar_clientes_prioritarios
     Sube a Prioritario a los clientes Estándar con al menos un año de
     antigüedad y compras de mayoreo en los últimos 12 meses.

   Usuario jam_api
     Login con los permisos mínimos que necesita la API. Requiere que la
     instancia acepte autenticación de SQL Server (modo mixto).
   ===================================================================== */

USE JAM_db;
GO

CREATE OR ALTER PROCEDURE dbo.sp_crear_pedido
    @id_store        INT,
    @id_customer     INT,
    @id_employee     INT           = NULL,
    @promotion_code  VARCHAR(20)   = NULL,
    @channel         VARCHAR(8),
    @items           NVARCHAR(MAX),          -- [{"id_variant": 1, "quantity": 2}, ...]
    @notes           NVARCHAR(255) = NULL,
    @source_node     VARCHAR(20)   = 'web_node',
    @delivery_type   VARCHAR(8)    = 'pickup',  -- pickup: recoger en sucursal | shipping: envío a domicilio
    @payment_method  VARCHAR(8)    = 'card',    -- card | transfer | cash | oxxo
    @street          NVARCHAR(100) = NULL,
    @ext_number      VARCHAR(10)   = NULL,
    @int_number      VARCHAR(10)   = NULL,
    @neighborhood    NVARCHAR(80)  = NULL,
    @city            NVARCHAR(60)  = NULL,
    @id_state        TINYINT       = NULL,
    @zip_code        CHAR(5)       = NULL,
    @reference       NVARCHAR(150) = NULL,
    @gift_wrap       BIT           = 0,         -- 1: empaque especial JAM para regalo
    @gift_occasion   VARCHAR(10)   = NULL,      -- birthday | friendship | christmas
    @gift_color      VARCHAR(8)    = NULL,      -- red | green | blue | pink | gold | silver | purple | black
    @gift_recipient  NVARCHAR(60)  = NULL,      -- para quién es (opcional)
    @gift_message    NVARCHAR(200) = NULL       -- dedicatoria (opcional)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    /* El envío cuesta $99; el monto para envío gratis depende del tipo de cuenta.
       Recoger en sucursal no cuesta. */
    DECLARE @costo_envio DECIMAL(8,2) = 99.00;
    DECLARE @costo_regalo DECIMAL(6,2) = 30.00;

    DECLARE @tier_code          VARCHAR(10),
            @tier_discount      DECIMAL(4,2),
            @envio_gratis_desde DECIMAL(10,2),
            @max_piezas         SMALLINT;

    DECLARE @lineas TABLE (
        id_variant  INT          NOT NULL PRIMARY KEY,
        quantity    SMALLINT     NOT NULL,
        unit_price  DECIMAL(8,2) NULL,
        is_outlet   BIT          NOT NULL DEFAULT 0
    );
    DECLARE @descontados TABLE (id_variant INT NOT NULL);

    /* ---------- Validaciones ---------- */
    IF @items IS NULL OR ISJSON(@items) = 0
        THROW 50001, N'La lista de artículos no es un JSON válido.', 1;

    IF @channel IS NULL OR @channel NOT IN ('in_store', 'online', 'whatsapp')
        THROW 50002, N'El canal debe ser in_store, online o whatsapp.', 1;

    IF EXISTS (
        SELECT 1
        FROM OPENJSON(@items) WITH (id_variant INT '$.id_variant', quantity INT '$.quantity')
        WHERE id_variant IS NULL OR quantity IS NULL OR quantity <= 0 OR quantity > 999)
        THROW 50003, N'Cada artículo necesita id_variant y una cantidad entre 1 y 999.', 1;

    INSERT INTO @lineas (id_variant, quantity)
    SELECT id_variant, SUM(quantity)
    FROM OPENJSON(@items) WITH (id_variant INT '$.id_variant', quantity INT '$.quantity')
    GROUP BY id_variant;

    IF NOT EXISTS (SELECT 1 FROM @lineas)
        THROW 50004, N'El pedido no tiene artículos.', 1;

    IF NOT EXISTS (SELECT 1 FROM dbo.store WHERE id_store = @id_store AND active = 1 AND deleted = 0)
        THROW 50005, N'La sucursal no existe o no está activa.', 1;

    SELECT @tier_code = t.code,
           @tier_discount = t.discount_percent,
           @envio_gratis_desde = t.free_shipping_from,
           @max_piezas = t.max_units_per_item
    FROM dbo.customer AS c
    JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
    WHERE c.id_customer = @id_customer AND c.deleted = 0;

    IF @tier_code IS NULL
        THROW 50006, N'El cliente no existe.', 1;

    IF EXISTS (SELECT 1 FROM @lineas WHERE quantity > @max_piezas)
    BEGIN
        DECLARE @msg_piezas NVARCHAR(200) = CONCAT(N'Tu cuenta permite hasta ', @max_piezas,
            N' piezas por artículo. Para compras de mayoreo solicita la cuenta Prioritaria.');
        THROW 50016, @msg_piezas, 1;
    END;

    IF @id_employee IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM dbo.employee WHERE id_employee = @id_employee AND id_store = @id_store AND deleted = 0)
        THROW 50007, N'El empleado no pertenece a la sucursal del pedido.', 1;

    IF @delivery_type IS NULL OR @delivery_type NOT IN ('pickup', 'shipping')
        THROW 50011, N'La entrega debe ser pickup (recoger en sucursal) o shipping (envío a domicilio).', 1;

    IF @delivery_type = 'pickup'
       AND EXISTS (SELECT 1 FROM dbo.store WHERE id_store = @id_store AND id_address IS NULL)
        THROW 50012, N'La tienda en línea no tiene mostrador: elija una sucursal para recoger o envío a domicilio.', 1;

    IF @delivery_type = 'shipping'
       AND (NULLIF(LTRIM(RTRIM(@street)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@ext_number)), '') IS NULL
            OR NULLIF(LTRIM(RTRIM(@neighborhood)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@city)), '') IS NULL
            OR @zip_code IS NULL OR @zip_code NOT LIKE '[0-9][0-9][0-9][0-9][0-9]'
            OR NOT EXISTS (SELECT 1 FROM dbo.state WHERE id_state = @id_state))
        THROW 50013, N'El domicilio de envío está incompleto: calle, número, colonia, ciudad, estado y código postal de 5 dígitos.', 1;

    IF @payment_method IS NULL OR @payment_method NOT IN ('card', 'transfer', 'cash', 'oxxo')
        THROW 50014, N'El método de pago debe ser card, transfer, cash u oxxo.', 1;

    IF @payment_method = 'cash' AND @delivery_type <> 'pickup'
        THROW 50015, N'El pago en efectivo solo aplica al recoger en sucursal.', 1;

    SET @gift_wrap = ISNULL(@gift_wrap, 0);
    SET @gift_recipient = NULLIF(LTRIM(RTRIM(@gift_recipient)), '');
    SET @gift_message = NULLIF(LTRIM(RTRIM(@gift_message)), '');

    IF @gift_wrap = 1 AND (@gift_occasion IS NULL OR @gift_occasion NOT IN ('birthday', 'friendship', 'christmas'))
        THROW 50017, N'Elige la ocasión del regalo: cumpleaños, amistad o Navidad.', 1;

    IF @gift_wrap = 1 AND (@gift_color IS NULL OR @gift_color NOT IN ('red', 'green', 'blue', 'pink', 'gold', 'silver', 'purple', 'black'))
        THROW 50018, N'Elige un color de papel para el regalo.', 1;

    IF @gift_wrap = 1 AND @gift_message IS NOT NULL AND @gift_recipient IS NULL
        THROW 50019, N'Para agregar una dedicatoria escribe para quién es el regalo.', 1;

    /* Precios desde el catálogo; el outlet ya trae su descuento en el precio. */
    UPDATE l
    SET l.unit_price = CASE WHEN p.outlet_percent IS NULL THEN p.base_price
                            ELSE ROUND(p.base_price * (100 - p.outlet_percent) / 100, 2) END,
        l.is_outlet = CASE WHEN p.outlet_percent IS NULL THEN 0 ELSE 1 END
    FROM @lineas AS l
    JOIN dbo.product_variant AS pv ON pv.id_variant = l.id_variant AND pv.deleted = 0
    JOIN dbo.product AS p ON p.id_product = pv.id_product AND p.deleted = 0;

    IF EXISTS (SELECT 1 FROM @lineas WHERE unit_price IS NULL)
        THROW 50008, N'Uno de los productos no existe o ya no está a la venta.', 1;

    /* Cupón vigente (fechas en UTC, igual que el resto de la base) */
    DECLARE @id_promotion INT = NULL,
            @discount_percent DECIMAL(5,2) = 0,
            @hoy DATE = CAST(SYSUTCDATETIME() AS DATE);

    IF NULLIF(LTRIM(RTRIM(@promotion_code)), '') IS NOT NULL
    BEGIN
        SELECT @id_promotion = id_promotion,
               @discount_percent = discount_percent
        FROM dbo.promotion
        WHERE code = LTRIM(RTRIM(@promotion_code))
          AND deleted = 0
          AND @hoy BETWEEN start_date AND end_date;

        IF @id_promotion IS NULL
            THROW 50009, N'El cupón no existe o no está vigente.', 1;
    END;

    /* Cupón + descuento del tipo de cuenta, con tope de 90 %. */
    DECLARE @porcentaje DECIMAL(5,2) =
        CASE WHEN @discount_percent + @tier_discount > 90 THEN 90 ELSE @discount_percent + @tier_discount END;

    DECLARE @subtotal DECIMAL(10,2) = (SELECT SUM(unit_price * quantity) FROM @lineas);
    DECLARE @con_descuento DECIMAL(10,2) = (SELECT ISNULL(SUM(unit_price * quantity), 0) FROM @lineas WHERE is_outlet = 0);
    DECLARE @discount DECIMAL(10,2) = ROUND(@con_descuento * @porcentaje / 100, 2);
    DECLARE @tier_amount DECIMAL(10,2) = ROUND(@con_descuento * @tier_discount / 100, 2);
    DECLARE @shipping DECIMAL(8,2) =
        CASE WHEN @delivery_type = 'shipping' AND @subtotal - @discount < @envio_gratis_desde THEN @costo_envio ELSE 0 END;
    DECLARE @gift_cost DECIMAL(6,2) = CASE WHEN @gift_wrap = 1 THEN @costo_regalo ELSE 0 END;
    DECLARE @total DECIMAL(10,2) = @subtotal - @discount + @shipping + @gift_cost;
    DECLARE @priority_shipping BIT =
        CASE WHEN @delivery_type = 'shipping' AND @tier_code IN ('priority', 'admin') THEN 1 ELSE 0 END;

    /* Pago simulado: la tarjeta se aprueba al momento; OXXO, transferencia
       y efectivo quedan pendientes. Nunca se guardan datos de la tarjeta. */
    DECLARE @payment_status VARCHAR(9) = CASE WHEN @payment_method = 'card' THEN 'completed' ELSE 'pending' END;
    DECLARE @order_status   VARCHAR(9) = CASE WHEN @payment_method = 'card' THEN 'paid' ELSE 'pending' END;

    DECLARE @id_order INT, @id_address INT = NULL;

    /* ---------- Transacción ---------- */
    BEGIN TRY
        BEGIN TRANSACTION;

        /* El UPDATE condicional bloquea las filas y solo descuenta si hay stock:
           dos compras simultáneas de la última pieza no pueden pasar ambas. */
        UPDATE i
        SET i.stock = i.stock - l.quantity
        OUTPUT inserted.id_variant INTO @descontados (id_variant)
        FROM dbo.inventory AS i
        JOIN @lineas AS l ON l.id_variant = i.id_variant
        WHERE i.id_store = @id_store
          AND i.deleted = 0
          AND i.stock >= l.quantity;

        IF (SELECT COUNT(*) FROM @descontados) <> (SELECT COUNT(*) FROM @lineas)
            THROW 50010, N'No hay existencias suficientes en la sucursal para uno o más artículos.', 1;

        IF @delivery_type = 'shipping'
        BEGIN
            INSERT INTO dbo.address (street, ext_number, int_number, neighborhood, city, id_state, zip_code, reference, source_node)
            VALUES (LTRIM(RTRIM(@street)), LTRIM(RTRIM(@ext_number)), NULLIF(LTRIM(RTRIM(@int_number)), ''),
                    LTRIM(RTRIM(@neighborhood)), LTRIM(RTRIM(@city)), @id_state, @zip_code, @reference, @source_node);
            SET @id_address = CAST(SCOPE_IDENTITY() AS INT);
        END;

        INSERT INTO dbo.customer_order
            (id_store, id_customer, id_employee, id_promotion, status, channel,
             subtotal, discount, total, delivery_type, id_address, shipping_cost, gift_cost, priority_shipping, notes, source_node)
        VALUES
            (@id_store, @id_customer, @id_employee, @id_promotion, @order_status, @channel,
             @subtotal, @discount, @total, @delivery_type, @id_address, @shipping, @gift_cost, @priority_shipping, @notes, @source_node);

        SET @id_order = CAST(SCOPE_IDENTITY() AS INT);

        IF @gift_wrap = 1
            INSERT INTO dbo.order_gift (id_order, occasion, wrap_color, recipient_name, message, source_node)
            VALUES (@id_order, @gift_occasion, @gift_color, @gift_recipient, @gift_message, @source_node);

        INSERT INTO dbo.order_item (id_order, id_variant, quantity, unit_price, source_node)
        SELECT @id_order, id_variant, quantity, unit_price, @source_node
        FROM @lineas;

        INSERT INTO dbo.payment (id_order, amount, currency, method, external_reference, status, source_node)
        VALUES (@id_order, @total, 'MXN', @payment_method,
                CASE WHEN @payment_method = 'card' THEN CONCAT('SIM-', FORMAT(@id_order, '000000')) END,
                @payment_status, @source_node);

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
        THROW;
    END CATCH;

    SELECT @id_order       AS id_order,
           @order_status   AS status,
           @payment_status AS payment_status,
           @subtotal       AS subtotal,
           @discount       AS discount,
           @tier_amount    AS tier_discount,
           @shipping       AS shipping_cost,
           @gift_cost      AS gift_cost,
           @total          AS total,
           @priority_shipping AS priority_shipping;
END;
GO

/* ---------------------------------------------------------------------
   Regla para ser cliente Prioritario
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_evaluar_clientes_prioritarios
    @dias_minimos   INT = 365,   -- antigüedad mínima como cliente
    @piezas_minimas INT = 50     -- piezas compradas en los últimos 12 meses
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @prioritario TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'priority'),
            @estandar    TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'standard');

    DECLARE @promovidos TABLE (id_customer INT NOT NULL PRIMARY KEY);

    UPDATE c
    SET c.id_account_tier = @prioritario
    OUTPUT inserted.id_customer INTO @promovidos (id_customer)
    FROM dbo.customer AS c
    WHERE c.deleted = 0
      AND c.id_account_tier = @estandar
      AND DATEDIFF(DAY, c.customer_since, CAST(SYSUTCDATETIME() AS DATE)) >= @dias_minimos
      AND (SELECT ISNULL(SUM(oi.quantity), 0)
           FROM dbo.customer_order AS co
           JOIN dbo.order_item AS oi ON oi.id_order = co.id_order AND oi.deleted = 0
           WHERE co.id_customer = c.id_customer
             AND co.deleted = 0
             AND co.status NOT IN ('cancelled', 'returned')
             AND co.order_date >= DATEADD(MONTH, -12, SYSUTCDATETIME())) >= @piezas_minimas;

    SELECT c.id_customer, c.first_name, c.last_name_paternal, c.email
    FROM @promovidos AS p
    JOIN dbo.customer AS c ON c.id_customer = p.id_customer
    ORDER BY c.id_customer;
END;
GO

/* Prueba: devuelve los clientes que cambiaron a Prioritario */
-- EXEC dbo.sp_evaluar_clientes_prioritarios;

/* Prueba: debe devolver el número de pedido y descontar 1 pieza del inventario 1 */
-- EXEC dbo.sp_crear_pedido @id_store = 1, @id_customer = 1, @channel = 'in_store',
--      @items = N'[{"id_variant": 1, "quantity": 1}]', @promotion_code = 'BIENVENIDA10';

/* ---------------------------------------------------------------------
   Usuario de la API con privilegios mínimos
   --------------------------------------------------------------------- */
USE master;
GO

/* La contraseña se escribe solo al ejecutar el script; no se guarda en el
   repositorio. Si el usuario ya existe, esta sección no hace nada. */
DECLARE @clave_api NVARCHAR(128) = N'';   -- >>> contraseña de jam_api (borrarla antes de subir a GitHub)

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'jam_api')
BEGIN
    IF LEN(@clave_api) = 0
        THROW 50020, N'Escriba la contraseña de jam_api en @clave_api y vuelva a ejecutar el script.', 1;

    DECLARE @crear NVARCHAR(MAX) = N'CREATE LOGIN jam_api WITH PASSWORD = ' + QUOTENAME(@clave_api, N'''')
        + N', DEFAULT_DATABASE = JAM_db, CHECK_POLICY = ON;';
    EXEC sys.sp_executesql @crear;
END;
GO

USE JAM_db;
GO

IF USER_ID(N'jam_api') IS NULL
    CREATE USER jam_api FOR LOGIN jam_api;
GO

/* Lectura y escritura de datos y ejecución de procedimientos; sin permisos
   para crear, modificar o borrar objetos (DDL). */
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::dbo  TO jam_api;
GRANT SELECT, INSERT, DELETE, EXECUTE         ON SCHEMA::dist TO jam_api;
GO

/* En los nodos: lectura y escritura (la sincronización reescribe los fragmentos). */
DECLARE @db SYSNAME, @sql NVARCHAR(MAX);

DECLARE nodos CURSOR LOCAL FAST_FORWARD FOR
    SELECT db_name FROM dist.node WHERE id_store IS NOT NULL;

OPEN nodos;
FETCH NEXT FROM nodos INTO @db;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @sql = N'USE ' + QUOTENAME(@db) + N';
        IF USER_ID(N''jam_api'') IS NULL CREATE USER jam_api FOR LOGIN jam_api;
        ALTER ROLE db_datareader ADD MEMBER jam_api;
        ALTER ROLE db_datawriter ADD MEMBER jam_api;';
    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM nodos INTO @db;
END;

CLOSE nodos;
DEALLOCATE nodos;
GO

/* Después de ejecutar este script, en api/v1/php/config/settings.php:
     'JAM_LOCAL_USER'     => 'jam_api',
     'JAM_LOCAL_PASSWORD' => '<la contraseña de arriba>',
*/
