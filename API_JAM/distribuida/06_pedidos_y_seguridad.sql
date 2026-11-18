/* =====================================================================
   JAM - Base de datos distribuida
   06. Registro de pedidos transaccional y usuario de la API
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql y 01_fragmentacion_horizontal.sql ejecutados.

   dbo.sp_crear_pedido
     Registra un pedido completo como una sola transacción:
       - valida sucursal, cliente, empleado, cupón y artículos;
       - toma los precios del catálogo (nunca del cliente);
       - descuenta el inventario de la sucursal solo si alcanza el stock;
       - inserta customer_order, order_item y payment;
       - registra el domicilio (envío) y el pago simulado;
     Si cualquier paso falla no queda nada a medias (XACT_ABORT + TRY/CATCH).
     Errores de negocio: THROW 50001-50015, la API los devuelve como HTTP 409.

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
    @reference       NVARCHAR(150) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    /* Envío: gratis desde $999 después del descuento; recoger en sucursal no cuesta. */
    DECLARE @envio_gratis_desde DECIMAL(10,2) = 999.00,
            @costo_envio        DECIMAL(8,2)  = 99.00;

    DECLARE @lineas TABLE (
        id_variant  INT          NOT NULL PRIMARY KEY,
        quantity    SMALLINT     NOT NULL,
        unit_price  DECIMAL(8,2) NULL
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

    IF NOT EXISTS (SELECT 1 FROM dbo.customer WHERE id_customer = @id_customer AND deleted = 0)
        THROW 50006, N'El cliente no existe.', 1;

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

    /* Precios desde el catálogo */
    UPDATE l
    SET l.unit_price = p.base_price
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

    DECLARE @subtotal DECIMAL(10,2) = (SELECT SUM(unit_price * quantity) FROM @lineas);
    DECLARE @discount DECIMAL(10,2) = ROUND(@subtotal * @discount_percent / 100, 2);
    DECLARE @shipping DECIMAL(8,2) =
        CASE WHEN @delivery_type = 'shipping' AND @subtotal - @discount < @envio_gratis_desde THEN @costo_envio ELSE 0 END;
    DECLARE @total DECIMAL(10,2) = @subtotal - @discount + @shipping;

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
             subtotal, discount, total, delivery_type, id_address, shipping_cost, notes, source_node)
        VALUES
            (@id_store, @id_customer, @id_employee, @id_promotion, @order_status, @channel,
             @subtotal, @discount, @total, @delivery_type, @id_address, @shipping, @notes, @source_node);

        SET @id_order = CAST(SCOPE_IDENTITY() AS INT);

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
           @shipping       AS shipping_cost,
           @total          AS total;
END;
GO

/* Prueba: debe devolver el número de pedido y descontar 1 pieza del inventario 1 */
-- EXEC dbo.sp_crear_pedido @id_store = 1, @id_customer = 1, @channel = 'in_store',
--      @items = N'[{"id_variant": 1, "quantity": 1}]', @promotion_code = 'BIENVENIDA10';

/* ---------------------------------------------------------------------
   Usuario de la API con privilegios mínimos
   --------------------------------------------------------------------- */
USE master;
GO

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'jam_api')
    CREATE LOGIN jam_api
        WITH PASSWORD = N'Cambiar_Esta_Clave_2026!',     -- >>> cambiar
             DEFAULT_DATABASE = JAM_db,
             CHECK_POLICY = ON;
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
