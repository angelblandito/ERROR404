/* =====================================================================
   JAM - Base de datos distribuida
   12. Usuarios de prueba
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql ejecutado (y 11 para la regla de Prioritario).

   Crea tres cuentas para probar la tienda. Inicia sesión con su correo
   y su contraseña:

     Tipo          Correo                          Contraseña
     ------------  ------------------------------  ------------------
     Estándar      prueba.estandar@jamsport.mx     JamEstandar2026
     Prioritario   prueba.prioritario@jamsport.mx  JamPrioritario2026
     Estándar *    prueba.listo@jamsport.mx        JamListo2026

     * Cuenta Estándar que YA cumple los requisitos (más de un año y más
       de $50,000 en compras): sirve para probar el botón "Subir a
       Prioritario". Si la subes y quieres repetir la prueba, vuelve a
       ejecutar este script: la regresa a Estándar.

   Cada cuenta trae pedidos entregados para que se vea su avance:
     - Estándar:    4 meses con la cuenta y $5,350 en compras.
     - Prioritario: 2 años con la cuenta y $61,750 en compras.
     - Listo:       14 meses con la cuenta y $53,500 en compras.

   Las fechas se calculan desde hoy, así siempre cumplen (o no) la regla.
   Las contraseñas solo se guardan cifradas (bcrypt). Son cuentas de
   prueba: bórralas antes de usar la tienda con clientes reales.
   Se puede ejecutar varias veces.
   ===================================================================== */

USE JAM_db;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   Auxiliar: crea un pedido entregado y pagado de un cliente
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'tempdb..#pedido_prueba') IS NOT NULL DROP PROCEDURE #pedido_prueba;
GO

/* Fragmentos de clientes y pedidos (solo si 01 y 02 ya se ejecutaron) */
IF OBJECT_ID(N'dist.sp_sincronizar_nodos', N'P') IS NOT NULL
    EXEC dist.sp_sincronizar_nodos;
GO
CREATE PROCEDURE #pedido_prueba
    @email     VARCHAR(100),
    @dias      INT,              -- hace cuántos días fue el pedido
    @sku1      VARCHAR(20),
    @cantidad1 SMALLINT,
    @sku2      VARCHAR(20) = NULL,
    @cantidad2 SMALLINT    = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @id_customer INT = (SELECT id_customer FROM dbo.customer WHERE email = @email AND deleted = 0);
    IF @id_customer IS NULL RETURN;

    DECLARE @fecha DATETIME2(3) = DATEADD(DAY, -@dias, SYSUTCDATETIME());
    DECLARE @nuevo TABLE (id_order INT);

    INSERT INTO dbo.customer_order (id_store, id_customer, order_date, status, channel, subtotal, discount, total,
                                    delivery_type, notes, source_node)
    OUTPUT inserted.id_order INTO @nuevo
    VALUES (4, @id_customer, @fecha, 'delivered', 'online', 0, 0, 0, 'pickup', N'Pedido de prueba', 'web_node');

    DECLARE @id_order INT = (SELECT id_order FROM @nuevo);

    INSERT INTO dbo.order_item (id_order, id_variant, quantity, unit_price, source_node)
    SELECT @id_order, pv.id_variant, x.cantidad, p.base_price, 'web_node'
    FROM (VALUES (@sku1, @cantidad1), (@sku2, @cantidad2)) AS x (sku, cantidad)
    JOIN dbo.product_variant AS pv ON pv.sku = x.sku AND pv.deleted = 0
    JOIN dbo.product AS p ON p.id_product = pv.id_product
    WHERE x.sku IS NOT NULL;

    DECLARE @subtotal DECIMAL(10,2) = (SELECT ISNULL(SUM(quantity * unit_price), 0) FROM dbo.order_item WHERE id_order = @id_order);

    UPDATE dbo.customer_order SET subtotal = @subtotal, total = @subtotal WHERE id_order = @id_order;

    INSERT INTO dbo.payment (id_order, amount, payment_date, method, external_reference, status, source_node)
    VALUES (@id_order, @subtotal, @fecha, 'card', 'PRUEBA-' + CAST(@id_order AS VARCHAR(10)), 'completed', 'web_node');
END;
GO

/* ---------------------------------------------------------------------
   1. Cuentas (si ya existen, solo se restablecen su tipo y contraseña)
   --------------------------------------------------------------------- */
DECLARE @estandar TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'standard'),
        @prioritario TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'priority');

DECLARE @cuentas TABLE (
    email VARCHAR(100), first_name NVARCHAR(50), last_name NVARCHAR(50), phone VARCHAR(15),
    birth_date DATE, sex CHAR(1), tier TINYINT, dias INT, hash VARCHAR(255));

INSERT INTO @cuentas VALUES
('prueba.estandar@jamsport.mx',    N'Sofía',    N'Ramírez', '6649990001', '2001-05-14', 'F', @estandar,    120,
 '$2y$10$IX/f94oewIo9YX6Er9BofeKycSDqsiGksSHtS7M4NXCthSc4n9mXq'),
('prueba.prioritario@jamsport.mx', N'Carlos',   N'Mendoza', '6649990002', '1987-09-02', 'M', @prioritario, 730,
 '$2y$10$kcUiw/DDqzjlnEdbnCVhtuLvrYKJjdHYY41LSN2DRrVHphdM98emi'),
('prueba.listo@jamsport.mx',       N'Valeria',  N'Torres',  '6649990003', '1994-02-21', 'F', @estandar,    425,
 '$2y$10$TuajWUw.VHamOvs8wAw8.eZ66rf.je1WGpOwaF8B7PRaG9iU/dq5i');

INSERT INTO dbo.customer (id_loyalty_level, id_account_tier, id_store, id_address, first_name, last_name_paternal,
                          last_name_maternal, birth_date, sex, phone, email, accepts_marketing, customer_since, source_node)
SELECT 1, c.tier, 4, NULL, c.first_name, c.last_name, N'Prueba', c.birth_date, c.sex, c.phone, c.email, 1,
       CAST(DATEADD(DAY, -c.dias, SYSUTCDATETIME()) AS DATE), 'web_node'
FROM @cuentas AS c
WHERE NOT EXISTS (SELECT 1 FROM dbo.customer AS x WHERE x.email = c.email AND x.deleted = 0);

UPDATE cu
SET cu.id_account_tier = c.tier,
    cu.customer_since = CAST(DATEADD(DAY, -c.dias, SYSUTCDATETIME()) AS DATE)
FROM dbo.customer AS cu
JOIN @cuentas AS c ON c.email = cu.email
WHERE cu.deleted = 0;

INSERT INTO dbo.customer_credential (id_customer, password_hash, terms_accepted_at)
SELECT cu.id_customer, c.hash, SYSUTCDATETIME()
FROM dbo.customer AS cu
JOIN @cuentas AS c ON c.email = cu.email
WHERE cu.deleted = 0
  AND NOT EXISTS (SELECT 1 FROM dbo.customer_credential AS x WHERE x.id_customer = cu.id_customer);

UPDATE cr
SET cr.password_hash = c.hash, cr.failed_attempts = 0, cr.locked_until = NULL, cr.updated_at = SYSUTCDATETIME()
FROM dbo.customer_credential AS cr
JOIN dbo.customer AS cu ON cu.id_customer = cr.id_customer
JOIN @cuentas AS c ON c.email = cu.email
WHERE cu.deleted = 0;
GO

/* ---------------------------------------------------------------------
   2. Pedidos de prueba (solo la primera vez)
   --------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.customer_order AS co JOIN dbo.customer AS c ON c.id_customer = co.id_customer
               WHERE c.email = 'prueba.estandar@jamsport.mx' AND co.notes = N'Pedido de prueba')
BEGIN
    -- Estándar: $3,700 + $1,650 = $5,350
    EXEC #pedido_prueba 'prueba.estandar@jamsport.mx', 90, 'JAM-STR-27-BLK', 2;
    EXEC #pedido_prueba 'prueba.estandar@jamsport.mx', 30, 'JAM-PLS-27-BLK', 1;
END;

IF NOT EXISTS (SELECT 1 FROM dbo.customer_order AS co JOIN dbo.customer AS c ON c.id_customer = co.id_customer
               WHERE c.email = 'prueba.prioritario@jamsport.mx' AND co.notes = N'Pedido de prueba')
BEGIN
    -- Prioritario (mayoreo): $37,000 + $24,750 = $61,750
    EXEC #pedido_prueba 'prueba.prioritario@jamsport.mx', 400, 'JAM-STR-27-BLK', 20;
    EXEC #pedido_prueba 'prueba.prioritario@jamsport.mx', 200, 'JAM-PLS-27-BLK', 15;
END;

IF NOT EXISTS (SELECT 1 FROM dbo.customer_order AS co JOIN dbo.customer AS c ON c.id_customer = co.id_customer
               WHERE c.email = 'prueba.listo@jamsport.mx' AND co.notes = N'Pedido de prueba')
BEGIN
    -- Listo para subir: $35,000 + $18,500 = $53,500
    EXEC #pedido_prueba 'prueba.listo@jamsport.mx', 300, 'JAM-STR-27-BLK', 10, 'JAM-PLS-27-BLK', 10;
    EXEC #pedido_prueba 'prueba.listo@jamsport.mx', 60, 'JAM-STR-27-WHT', 10;
END;
GO

DROP PROCEDURE #pedido_prueba;
GO

/* Fragmentos de clientes y pedidos (solo si 01 y 02 ya se ejecutaron) */
IF OBJECT_ID(N'dist.sp_sincronizar_nodos', N'P') IS NOT NULL
    EXEC dist.sp_sincronizar_nodos;
GO

/* Resultado esperado: tres cuentas con su tipo, antigüedad y compras */
SELECT c.email, t.name AS tipo_de_cuenta, c.customer_since AS cliente_desde,
       DATEDIFF(DAY, c.customer_since, CAST(SYSUTCDATETIME() AS DATE)) AS dias,
       (SELECT ISNULL(SUM(co.total), 0) FROM dbo.customer_order AS co
        WHERE co.id_customer = c.id_customer AND co.deleted = 0
          AND co.status IN ('paid', 'shipped', 'delivered')) AS comprado
FROM dbo.customer AS c
JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
WHERE c.email LIKE 'prueba.%@jamsport.mx' AND c.deleted = 0
ORDER BY c.email;
GO
