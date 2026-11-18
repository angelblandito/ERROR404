/* =====================================================================
   JAM - Base de datos distribuida
   01. Nodos y fragmentación horizontal
   ---------------------------------------------------------------------
   Requisitos: haber ejecutado API_JAM/db_jam.sql (crea JAM_db).
   Instancia recomendada: SQL Server 2022 Developer (funciona también
   en Express para esta parte).

   Nodos:
     JAM_db       Nodo central (administración, catálogo maestro)
     JAM_tijuana  Sucursal Tijuana          id_store = 1
     JAM_cdmx     Sucursal CDMX Reforma     id_store = 2
     JAM_online   Tienda en línea           id_store = 4

   Fragmentación horizontal primaria (predicado id_store = N):
     employee, inventory, customer_order
   Fragmentación horizontal derivada (por la FK a customer_order):
     order_item, payment

   El script es re-ejecutable: no borra datos de JAM_db.
   ===================================================================== */

USE master;
GO

IF DB_ID(N'JAM_tijuana') IS NULL CREATE DATABASE JAM_tijuana COLLATE Modern_Spanish_CI_AS;
GO
IF DB_ID(N'JAM_cdmx') IS NULL CREATE DATABASE JAM_cdmx COLLATE Modern_Spanish_CI_AS;
GO
IF DB_ID(N'JAM_online') IS NULL CREATE DATABASE JAM_online COLLATE Modern_Spanish_CI_AS;
GO

USE JAM_db;
GO

IF SCHEMA_ID(N'dist') IS NULL EXEC (N'CREATE SCHEMA dist');
GO

/* ---------------------------------------------------------------------
   Catálogo de nodos (esquema de asignación)
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dist.node', N'U') IS NULL
CREATE TABLE dist.node (
    node_name    VARCHAR(20)  NOT NULL PRIMARY KEY,
    db_name      SYSNAME      NOT NULL,
    id_store     INT          NULL,
    node_role    VARCHAR(20)  NOT NULL,
    description  VARCHAR(150) NULL,
    CONSTRAINT uq_node_db_name UNIQUE (db_name),
    CONSTRAINT ck_node_role CHECK (node_role IN ('central','sucursal','tienda_en_linea','nube'))
);
GO

MERGE dist.node AS t
USING (VALUES
    ('central', 'JAM_db',      NULL, 'central',         'Nodo central: administración y catálogo maestro'),
    ('tijuana', 'JAM_tijuana', 1,    'sucursal',        'Sucursal JAM Tijuana Flagship'),
    ('cdmx',    'JAM_cdmx',    2,    'sucursal',        'Sucursal JAM CDMX Reforma'),
    ('online',  'JAM_online',  4,    'tienda_en_linea', 'JAM Online Store')
) AS s (node_name, db_name, id_store, node_role, description)
ON t.node_name = s.node_name
WHEN MATCHED THEN UPDATE SET db_name = s.db_name, id_store = s.id_store, node_role = s.node_role, description = s.description
WHEN NOT MATCHED THEN INSERT (node_name, db_name, id_store, node_role, description)
                      VALUES (s.node_name, s.db_name, s.id_store, s.node_role, s.description);
GO

/* ---------------------------------------------------------------------
   Creación de las tablas fragmento en cada nodo.
   Las PK conservan el id global de JAM_db (sin IDENTITY) para que la
   reconstrucción por UNION ALL no tenga choques de llaves.
   El CHECK (id_store = N) documenta y garantiza el predicado del fragmento.
   --------------------------------------------------------------------- */
DECLARE @db SYSNAME, @id INT, @sql NVARCHAR(MAX);

DECLARE nodos CURSOR LOCAL FAST_FORWARD FOR
    SELECT db_name, id_store FROM dist.node WHERE id_store IS NOT NULL;

OPEN nodos;
FETCH NEXT FROM nodos INTO @db, @id;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @sql = N'
IF OBJECT_ID(N''{db}.dbo.employee'', N''U'') IS NULL
CREATE TABLE {db}.dbo.employee (
    id_employee            INT          NOT NULL,
    id_store               INT          NOT NULL,
    id_role                TINYINT      NOT NULL,
    first_name             NVARCHAR(50)  NOT NULL,
    last_name_paternal     NVARCHAR(50)  NOT NULL,
    last_name_maternal     NVARCHAR(50)  NULL,
    phone                  VARCHAR(15)  NOT NULL,
    phone_verified         BIT          NOT NULL,
    email                  VARCHAR(100) NOT NULL,
    password_hash          VARCHAR(100) NOT NULL,
    must_change_password   BIT          NOT NULL,
    hire_date              DATE         NOT NULL,
    source_node            VARCHAR(20)  NOT NULL,
    updated_at             DATETIME2(3)     NOT NULL,
    deleted                BIT          NOT NULL,
    CONSTRAINT pk_employee PRIMARY KEY (id_employee),
    CONSTRAINT ck_employee_fragment CHECK (id_store = {id})
);

IF OBJECT_ID(N''{db}.dbo.inventory'', N''U'') IS NULL
CREATE TABLE {db}.dbo.inventory (
    id_inventory  INT          NOT NULL,
    id_store      INT          NOT NULL,
    id_variant    INT          NOT NULL,
    stock         INT          NOT NULL,
    min_stock     INT          NOT NULL,
    source_node   VARCHAR(20)  NOT NULL,
    updated_at    DATETIME2(3)     NOT NULL,
    deleted       BIT          NOT NULL,
    CONSTRAINT pk_inventory PRIMARY KEY (id_inventory),
    CONSTRAINT uq_inventory_store_variant UNIQUE (id_store, id_variant),
    CONSTRAINT ck_inventory_fragment CHECK (id_store = {id})
);

IF OBJECT_ID(N''{db}.dbo.customer_order'', N''U'') IS NULL
CREATE TABLE {db}.dbo.customer_order (
    id_order       INT           NOT NULL,
    id_store       INT           NOT NULL,
    id_customer    INT           NOT NULL,
    id_employee    INT           NULL,
    id_promotion   INT           NULL,
    order_date     DATETIME2(3)      NOT NULL,
    status         VARCHAR(9)    NOT NULL,
    channel        VARCHAR(8)    NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    discount       DECIMAL(10,2) NOT NULL,
    total          DECIMAL(10,2) NOT NULL,
    notes          NVARCHAR(255)  NULL,
    source_node    VARCHAR(20)   NOT NULL,
    updated_at     DATETIME2(3)      NOT NULL,
    deleted        BIT           NOT NULL,
    CONSTRAINT pk_customer_order PRIMARY KEY (id_order),
    CONSTRAINT ck_customer_order_fragment CHECK (id_store = {id})
);

IF OBJECT_ID(N''{db}.dbo.order_item'', N''U'') IS NULL
CREATE TABLE {db}.dbo.order_item (
    id_item        INT          NOT NULL,
    id_order       INT          NOT NULL,
    id_variant     INT          NOT NULL,
    quantity       SMALLINT     NOT NULL,
    unit_price     DECIMAL(8,2) NOT NULL,
    source_node    VARCHAR(20)  NOT NULL,
    updated_at     DATETIME2(3)     NOT NULL,
    deleted        BIT          NOT NULL,
    CONSTRAINT pk_order_item PRIMARY KEY (id_item),
    CONSTRAINT fk_order_item_order FOREIGN KEY (id_order) REFERENCES dbo.customer_order (id_order)
);

IF OBJECT_ID(N''{db}.dbo.payment'', N''U'') IS NULL
CREATE TABLE {db}.dbo.payment (
    id_payment          INT           NOT NULL,
    id_order            INT           NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    currency            CHAR(3)       NOT NULL,
    payment_date        DATETIME2(3)      NOT NULL,
    method              VARCHAR(8)    NOT NULL,
    external_reference  VARCHAR(50)   NULL,
    status              VARCHAR(9)    NOT NULL,
    source_node         VARCHAR(20)   NOT NULL,
    updated_at          DATETIME2(3)      NOT NULL,
    deleted             BIT           NOT NULL,
    CONSTRAINT pk_payment PRIMARY KEY (id_payment),
    CONSTRAINT fk_payment_order FOREIGN KEY (id_order) REFERENCES dbo.customer_order (id_order)
);';

    SET @sql = REPLACE(REPLACE(@sql, N'{db}', QUOTENAME(@db)), N'{id}', CAST(@id AS NVARCHAR(10)));

    /* Las FK de order_item y payment apuntan a la tabla del mismo nodo:
       se ejecuta dentro del contexto de la base del nodo. */
    SET @sql = N'USE ' + QUOTENAME(@db) + N'; ' + @sql;
    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM nodos INTO @db, @id;
END

CLOSE nodos;
DEALLOCATE nodos;
GO

/* ---------------------------------------------------------------------
   Distribución de los datos: copia de JAM_db a cada fragmento.
   Cada nodo se refresca en su propia transacción (todo o nada).
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_refrescar_fragmentos_horizontales
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @db SYSNAME, @id INT, @sql NVARCHAR(MAX);

    DECLARE nodos CURSOR LOCAL FAST_FORWARD FOR
        SELECT db_name, id_store FROM dist.node WHERE id_store IS NOT NULL;

    OPEN nodos;
    FETCH NEXT FROM nodos INTO @db, @id;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @sql = REPLACE(N'
BEGIN TRANSACTION;

DELETE FROM {db}.dbo.payment;
DELETE FROM {db}.dbo.order_item;
DELETE FROM {db}.dbo.customer_order;
DELETE FROM {db}.dbo.inventory;
DELETE FROM {db}.dbo.employee;

INSERT INTO {db}.dbo.employee
    (id_employee, id_store, id_role, first_name, last_name_paternal, last_name_maternal, phone, phone_verified,
     email, password_hash, must_change_password, hire_date, source_node, updated_at, deleted)
SELECT id_employee, id_store, id_role, first_name, last_name_paternal, last_name_maternal, phone, phone_verified,
       email, password_hash, must_change_password, hire_date, source_node, updated_at, deleted
FROM JAM_db.dbo.employee
WHERE id_store = @id;

INSERT INTO {db}.dbo.inventory
    (id_inventory, id_store, id_variant, stock, min_stock, source_node, updated_at, deleted)
SELECT id_inventory, id_store, id_variant, stock, min_stock, source_node, updated_at, deleted
FROM JAM_db.dbo.inventory
WHERE id_store = @id;

INSERT INTO {db}.dbo.customer_order
    (id_order, id_store, id_customer, id_employee, id_promotion, order_date, status, channel,
     subtotal, discount, total, notes, source_node, updated_at, deleted)
SELECT id_order, id_store, id_customer, id_employee, id_promotion, order_date, status, channel,
       subtotal, discount, total, notes, source_node, updated_at, deleted
FROM JAM_db.dbo.customer_order
WHERE id_store = @id;

INSERT INTO {db}.dbo.order_item
    (id_item, id_order, id_variant, quantity, unit_price, source_node, updated_at, deleted)
SELECT oi.id_item, oi.id_order, oi.id_variant, oi.quantity, oi.unit_price, oi.source_node, oi.updated_at, oi.deleted
FROM JAM_db.dbo.order_item AS oi
JOIN JAM_db.dbo.customer_order AS co ON co.id_order = oi.id_order
WHERE co.id_store = @id;

INSERT INTO {db}.dbo.payment
    (id_payment, id_order, amount, currency, payment_date, method, external_reference, status,
     source_node, updated_at, deleted)
SELECT p.id_payment, p.id_order, p.amount, p.currency, p.payment_date, p.method, p.external_reference, p.status,
       p.source_node, p.updated_at, p.deleted
FROM JAM_db.dbo.payment AS p
JOIN JAM_db.dbo.customer_order AS co ON co.id_order = p.id_order
WHERE co.id_store = @id;

COMMIT TRANSACTION;', N'{db}', QUOTENAME(@db));

        EXEC sys.sp_executesql @sql, N'@id INT', @id = @id;

        FETCH NEXT FROM nodos INTO @db, @id;
    END

    CLOSE nodos;
    DEALLOCATE nodos;
END;
GO

EXEC dist.sp_refrescar_fragmentos_horizontales;
GO

/* ---------------------------------------------------------------------
   Reconstrucción: la relación global se obtiene con UNION ALL de los
   fragmentos (vistas particionadas distribuidas).
   --------------------------------------------------------------------- */
CREATE OR ALTER VIEW dist.vw_inventory_global AS
    SELECT 'tijuana' AS node_name, * FROM JAM_tijuana.dbo.inventory
    UNION ALL
    SELECT 'cdmx'    AS node_name, * FROM JAM_cdmx.dbo.inventory
    UNION ALL
    SELECT 'online'  AS node_name, * FROM JAM_online.dbo.inventory;
GO

CREATE OR ALTER VIEW dist.vw_customer_order_global AS
    SELECT 'tijuana' AS node_name, * FROM JAM_tijuana.dbo.customer_order
    UNION ALL
    SELECT 'cdmx'    AS node_name, * FROM JAM_cdmx.dbo.customer_order
    UNION ALL
    SELECT 'online'  AS node_name, * FROM JAM_online.dbo.customer_order;
GO

CREATE OR ALTER VIEW dist.vw_employee_global AS
    SELECT 'tijuana' AS node_name, * FROM JAM_tijuana.dbo.employee
    UNION ALL
    SELECT 'cdmx'    AS node_name, * FROM JAM_cdmx.dbo.employee
    UNION ALL
    SELECT 'online'  AS node_name, * FROM JAM_online.dbo.employee;
GO

/* Resultado esperado: un renglón por nodo con sus conteos */
SELECT node_name, COUNT(*) AS inventory_rows FROM dist.vw_inventory_global GROUP BY node_name;
SELECT node_name, COUNT(*) AS order_rows FROM dist.vw_customer_order_global GROUP BY node_name;
GO
