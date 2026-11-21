/* =====================================================================
   JAM - Base de datos distribuida
   04. Espejo en Azure SQL (geo-replicación con grupo de conmutación)
   ---------------------------------------------------------------------
   Arquitectura:
     jam-sql-principal  (región A)  JAM_cloud  rol PRIMARY  lectura/escritura
     jam-sql-espejo     (región B)  JAM_cloud  rol SECONDARY  copia espejo
     jam-fog            grupo de conmutación por error que agrupa ambos

   Puntos de conexión del grupo:
     jam-fog.database.windows.net            siempre apunta al PRIMARY
     jam-fog.secondary.database.windows.net  siempre apunta al SECONDARY (solo lectura)

   Cómo llegan los datos:
     JAM_db (local) --API--> JAM_cloud en jam-sql-principal --geo-replicación--> jam-sql-espejo
     SQL Server Express no puede publicar replicación, así que la API copia
     las tablas de JAM_db a JAM_cloud (botón "Copiar al espejo" del panel y
     después de cada escritura). Azure replica JAM_cloud al segundo servidor
     por su cuenta.

   Para qué sirve:
     Si una sucursal (JAM_tijuana, JAM_cdmx o JAM_online) se cae, la API lee
     sus datos de JAM_cloud con el mismo predicado id_store = N. Si además
     jam-sql-principal no responde, la conexión pasa a jam-sql-espejo.

   Los servidores, la base y el grupo se crean con Azure CLI o el portal
   (ver docs/GUIA_DISTRIBUIDA_Y_NUBE.md). Este archivo se ejecuta conectado
   a la base JAM_cloud a través de jam-fog.database.windows.net.
   ===================================================================== */

/* ---------------------------------------------------------------------
   0. Tablas del espejo
      Mismas columnas que en JAM_db, sin IDENTITY ni llaves foráneas: el
      espejo guarda los id originales y solo recibe copias del nodo central.
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.role', N'U') IS NULL
CREATE TABLE dbo.role (
    id_role  TINYINT       NOT NULL,
    code     VARCHAR(13)   NOT NULL,
    name     NVARCHAR(20)  NOT NULL,
    CONSTRAINT pk_role PRIMARY KEY (id_role)
);
GO

IF OBJECT_ID(N'dbo.store_type', N'U') IS NULL
CREATE TABLE dbo.store_type (
    id_store_type  TINYINT       NOT NULL,
    code           VARCHAR(8)    NOT NULL,
    name           NVARCHAR(30)  NOT NULL,
    CONSTRAINT pk_store_type PRIMARY KEY (id_store_type)
);
GO

IF OBJECT_ID(N'dbo.category', N'U') IS NULL
CREATE TABLE dbo.category (
    id_category  TINYINT       NOT NULL,
    code         VARCHAR(11)   NOT NULL,
    name         NVARCHAR(20)  NOT NULL,
    department   VARCHAR(11)   NOT NULL,
    CONSTRAINT pk_category PRIMARY KEY (id_category)
);
GO

IF OBJECT_ID(N'dbo.size', N'U') IS NULL
CREATE TABLE dbo.size (
    id_size  TINYINT       NOT NULL,
    code     VARCHAR(3)    NOT NULL,
    name     NVARCHAR(20)  NOT NULL,
    CONSTRAINT pk_size PRIMARY KEY (id_size)
);
GO

IF OBJECT_ID(N'dbo.color', N'U') IS NULL
CREATE TABLE dbo.color (
    id_color  TINYINT       NOT NULL,
    code      VARCHAR(5)    NOT NULL,
    name      NVARCHAR(15)  NOT NULL,
    CONSTRAINT pk_color PRIMARY KEY (id_color)
);
GO

IF OBJECT_ID(N'dbo.loyalty_level', N'U') IS NULL
CREATE TABLE dbo.loyalty_level (
    id_loyalty_level  TINYINT        NOT NULL,
    code              VARCHAR(6)     NOT NULL,
    name              NVARCHAR(10)   NOT NULL,
    discount_percent  DECIMAL(4,2)   NOT NULL,
    min_spent         DECIMAL(10,2)  NOT NULL,
    CONSTRAINT pk_loyalty_level PRIMARY KEY (id_loyalty_level)
);
GO

IF OBJECT_ID(N'dbo.account_tier', N'U') IS NULL
CREATE TABLE dbo.account_tier (
    id_account_tier     TINYINT        NOT NULL,
    code                VARCHAR(10)    NOT NULL,
    name                NVARCHAR(20)   NOT NULL,
    discount_percent    DECIMAL(4,2)   NOT NULL,
    free_shipping_from  DECIMAL(10,2)  NOT NULL,
    delivery_days       NVARCHAR(30)   NOT NULL,
    max_units_per_item  SMALLINT       NOT NULL,
    support_minutes     SMALLINT       NOT NULL,
    can_manage_store    BIT            NOT NULL,
    CONSTRAINT pk_account_tier PRIMARY KEY (id_account_tier)
);
GO

IF OBJECT_ID(N'dbo.absence_type', N'U') IS NULL
CREATE TABLE dbo.absence_type (
    id_absence_type  TINYINT       NOT NULL,
    code             VARCHAR(11)   NOT NULL,
    name             NVARCHAR(20)  NOT NULL,
    CONSTRAINT pk_absence_type PRIMARY KEY (id_absence_type)
);
GO

IF OBJECT_ID(N'dbo.state', N'U') IS NULL
CREATE TABLE dbo.state (
    id_state  TINYINT       NOT NULL,
    code      CHAR(3)       NOT NULL,
    name      NVARCHAR(20)  NOT NULL,
    CONSTRAINT pk_state PRIMARY KEY (id_state)
);
GO

IF OBJECT_ID(N'dbo.season_theme', N'U') IS NULL
CREATE TABLE dbo.season_theme (
    id_theme           TINYINT        NOT NULL,
    code               VARCHAR(20)    NOT NULL,
    name               NVARCHAR(40)   NOT NULL,
    theme_type         VARCHAR(8)     NOT NULL,
    start_month        TINYINT        NOT NULL,
    start_day          TINYINT        NOT NULL,
    end_month          TINYINT        NOT NULL,
    end_day            TINYINT        NOT NULL,
    priority           TINYINT        NOT NULL,
    color_primary      CHAR(7)        NOT NULL,
    color_secondary    CHAR(7)        NOT NULL,
    color_accent       CHAR(7)        NOT NULL,
    color_accent_text  CHAR(7)        NOT NULL,
    hero_title         NVARCHAR(80)   NOT NULL,
    hero_text          NVARCHAR(160)  NOT NULL,
    effect             VARCHAR(10)    NOT NULL,
    active             BIT            NOT NULL,
    CONSTRAINT pk_season_theme PRIMARY KEY (id_theme)
);
GO

IF OBJECT_ID(N'dbo.store', N'U') IS NULL
CREATE TABLE dbo.store (
    id_store       INT           NOT NULL,
    id_owner       INT           NOT NULL,
    id_store_type  TINYINT       NOT NULL,
    id_address     INT           NULL,
    store_name     NVARCHAR(80)  NOT NULL,
    tax_id         VARCHAR(13)   NOT NULL,
    contact_phone  VARCHAR(15)   NOT NULL,
    email          VARCHAR(100)  NOT NULL,
    whatsapp       VARCHAR(15)   NOT NULL,
    opened_at      DATE          NOT NULL,
    active         BIT           NOT NULL,
    source_node    VARCHAR(20)   NOT NULL,
    updated_at     DATETIME2(3)  NOT NULL,
    deleted        BIT           NOT NULL,
    CONSTRAINT pk_store PRIMARY KEY (id_store)
);
GO

IF OBJECT_ID(N'dbo.store_config', N'U') IS NULL
CREATE TABLE dbo.store_config (
    id_store      INT           NOT NULL,
    config_key    VARCHAR(50)   NOT NULL,
    config_value  VARCHAR(255)  NOT NULL,
    source_node   VARCHAR(20)   NOT NULL,
    updated_at    DATETIME2(3)  NOT NULL,
    deleted       BIT           NOT NULL,
    CONSTRAINT pk_store_config PRIMARY KEY (id_store, config_key)
);
GO

IF OBJECT_ID(N'dbo.product', N'U') IS NULL
CREATE TABLE dbo.product (
    id_product      INT            NOT NULL,
    id_category     TINYINT        NOT NULL,
    name            NVARCHAR(80)   NOT NULL,
    description     NVARCHAR(255)  NULL,
    target_gender   CHAR(1)        NOT NULL,
    base_price      DECIMAL(8,2)   NOT NULL,
    age_segment     VARCHAR(5)     NOT NULL,
    image_url       NVARCHAR(255)  NULL,
    outlet_percent  TINYINT        NULL,
    source_node     VARCHAR(20)    NOT NULL,
    updated_at      DATETIME2(3)   NOT NULL,
    deleted         BIT            NOT NULL,
    CONSTRAINT pk_product PRIMARY KEY (id_product)
);
GO

IF OBJECT_ID(N'dbo.product_variant', N'U') IS NULL
CREATE TABLE dbo.product_variant (
    id_variant   INT           NOT NULL,
    id_product   INT           NOT NULL,
    id_size      TINYINT       NOT NULL,
    id_color     TINYINT       NOT NULL,
    sku          VARCHAR(20)   NOT NULL,
    barcode      CHAR(13)      NULL,
    source_node  VARCHAR(20)   NOT NULL,
    updated_at   DATETIME2(3)  NOT NULL,
    deleted      BIT           NOT NULL,
    CONSTRAINT pk_product_variant PRIMARY KEY (id_variant)
);
GO

IF OBJECT_ID(N'dbo.promotion', N'U') IS NULL
CREATE TABLE dbo.promotion (
    id_promotion      INT            NOT NULL,
    code              VARCHAR(20)    NOT NULL,
    description       NVARCHAR(150)  NULL,
    discount_percent  DECIMAL(5,2)   NOT NULL,
    start_date        DATE           NOT NULL,
    end_date          DATE           NOT NULL,
    source_node       VARCHAR(20)    NOT NULL,
    updated_at        DATETIME2(3)   NOT NULL,
    deleted           BIT            NOT NULL,
    CONSTRAINT pk_promotion PRIMARY KEY (id_promotion)
);
GO

IF OBJECT_ID(N'dbo.employee', N'U') IS NULL
CREATE TABLE dbo.employee (
    id_employee           INT           NOT NULL,
    id_store              INT           NOT NULL,
    id_role               TINYINT       NOT NULL,
    first_name            NVARCHAR(50)  NOT NULL,
    last_name_paternal    NVARCHAR(50)  NOT NULL,
    last_name_maternal    NVARCHAR(50)  NULL,
    phone                 VARCHAR(15)   NOT NULL,
    phone_verified        BIT           NOT NULL,
    email                 VARCHAR(100)  NOT NULL,
    password_hash         VARCHAR(100)  NOT NULL,
    must_change_password  BIT           NOT NULL,
    hire_date             DATE          NOT NULL,
    source_node           VARCHAR(20)   NOT NULL,
    updated_at            DATETIME2(3)  NOT NULL,
    deleted               BIT           NOT NULL,
    CONSTRAINT pk_employee PRIMARY KEY (id_employee)
);
GO

IF OBJECT_ID(N'dbo.inventory', N'U') IS NULL
CREATE TABLE dbo.inventory (
    id_inventory  INT           NOT NULL,
    id_store      INT           NOT NULL,
    id_variant    INT           NOT NULL,
    stock         INT           NOT NULL,
    min_stock     INT           NOT NULL,
    source_node   VARCHAR(20)   NOT NULL,
    updated_at    DATETIME2(3)  NOT NULL,
    deleted       BIT           NOT NULL,
    CONSTRAINT pk_inventory PRIMARY KEY (id_inventory)
);
GO

IF OBJECT_ID(N'dbo.customer', N'U') IS NULL
CREATE TABLE dbo.customer (
    id_customer         INT           NOT NULL,
    id_loyalty_level    TINYINT       NOT NULL,
    id_account_tier     TINYINT       NOT NULL,
    id_store            INT           NOT NULL,
    id_address          INT           NULL,
    first_name          NVARCHAR(50)  NOT NULL,
    last_name_paternal  NVARCHAR(50)  NOT NULL,
    last_name_maternal  NVARCHAR(50)  NULL,
    birth_date          DATE          NOT NULL,
    sex                 CHAR(1)       NOT NULL,
    phone               VARCHAR(15)   NOT NULL,
    email               VARCHAR(100)  NOT NULL,
    accepts_marketing   BIT           NOT NULL,
    customer_since      DATE          NOT NULL,
    source_node         VARCHAR(20)   NOT NULL,
    updated_at          DATETIME2(3)  NOT NULL,
    deleted             BIT           NOT NULL,
    CONSTRAINT pk_customer PRIMARY KEY (id_customer)
);
GO

IF OBJECT_ID(N'dbo.customer_order', N'U') IS NULL
CREATE TABLE dbo.customer_order (
    id_order           INT            NOT NULL,
    id_store           INT            NOT NULL,
    id_customer        INT            NOT NULL,
    id_employee        INT            NULL,
    id_promotion       INT            NULL,
    order_date         DATETIME2(3)   NOT NULL,
    status             VARCHAR(9)     NOT NULL,
    channel            VARCHAR(8)     NOT NULL,
    subtotal           DECIMAL(10,2)  NOT NULL,
    discount           DECIMAL(10,2)  NOT NULL,
    total              DECIMAL(10,2)  NOT NULL,
    delivery_type      VARCHAR(8)     NOT NULL,
    id_address         INT            NULL,
    shipping_cost      DECIMAL(8,2)   NOT NULL,
    priority_shipping  BIT            NOT NULL,
    gift_cost          DECIMAL(6,2)   NOT NULL,
    notes              NVARCHAR(255)  NULL,
    source_node        VARCHAR(20)    NOT NULL,
    updated_at         DATETIME2(3)   NOT NULL,
    deleted            BIT            NOT NULL,
    CONSTRAINT pk_customer_order PRIMARY KEY (id_order)
);
GO

IF OBJECT_ID(N'dbo.order_item', N'U') IS NULL
CREATE TABLE dbo.order_item (
    id_item      INT           NOT NULL,
    id_order     INT           NOT NULL,
    id_variant   INT           NOT NULL,
    quantity     SMALLINT      NOT NULL,
    unit_price   DECIMAL(8,2)  NOT NULL,
    source_node  VARCHAR(20)   NOT NULL,
    updated_at   DATETIME2(3)  NOT NULL,
    deleted      BIT           NOT NULL,
    CONSTRAINT pk_order_item PRIMARY KEY (id_item)
);
GO

IF OBJECT_ID(N'dbo.payment', N'U') IS NULL
CREATE TABLE dbo.payment (
    id_payment          INT            NOT NULL,
    id_order            INT            NOT NULL,
    amount              DECIMAL(10,2)  NOT NULL,
    currency            CHAR(3)        NOT NULL,
    payment_date        DATETIME2(3)   NOT NULL,
    method              VARCHAR(8)     NOT NULL,
    external_reference  VARCHAR(50)    NULL,
    status              VARCHAR(9)     NOT NULL,
    source_node         VARCHAR(20)    NOT NULL,
    updated_at          DATETIME2(3)   NOT NULL,
    deleted             BIT            NOT NULL,
    CONSTRAINT pk_payment PRIMARY KEY (id_payment)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_inventory_store')
    CREATE INDEX ix_inventory_store ON dbo.inventory (id_store);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_customer_order_store')
    CREATE INDEX ix_customer_order_store ON dbo.customer_order (id_store);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_employee_store')
    CREATE INDEX ix_employee_store ON dbo.employee (id_store);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_order_item_order')
    CREATE INDEX ix_order_item_order ON dbo.order_item (id_order);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_payment_order')
    CREATE INDEX ix_payment_order ON dbo.payment (id_order);
GO

/* Bitácora de la copia: cuántos renglones y hasta qué updated_at llegó cada tabla. */
IF OBJECT_ID(N'dbo.mirror_sync', N'U') IS NULL
CREATE TABLE dbo.mirror_sync (
    table_name   SYSNAME       NOT NULL,
    rows_copied  INT           NOT NULL,
    watermark    DATETIME2(3)  NULL,
    synced_at    DATETIME2(3)  NOT NULL,
    CONSTRAINT pk_mirror_sync PRIMARY KEY (table_name)
);
GO

/* 1. ¿En qué servidor estoy y la base acepta escrituras?
      PRIMARY  -> READ_WRITE
      SECONDARY (conectado por .secondary) -> READ_ONLY */
SELECT
    @@SERVERNAME                                   AS servidor,
    DB_NAME()                                      AS base,
    DATABASEPROPERTYEX(DB_NAME(), 'Updateability') AS modo;
GO

/* 2. Estado del enlace de geo-replicación (espejo) */
SELECT
    partner_server,
    partner_database,
    role_desc,
    replication_state_desc,
    last_replication,
    replication_lag_sec,
    secondary_allow_connections_desc
FROM sys.dm_geo_replication_link_status;
GO

/* 3. Prueba de sincronización del espejo
      a) Conectado al PRIMARY (jam-fog.database.windows.net):           */
SELECT TOP (5) id_product, name, base_price, updated_at
FROM dbo.product
ORDER BY updated_at DESC;
GO
/*    b) Conectarse a jam-fog.secondary.database.windows.net y ejecutar la
         misma consulta: debe devolver los mismos datos unos segundos después.

   4. Prueba de conmutación (failover planeado, sin pérdida de datos):
         az sql failover-group set-primary --name jam-fog --resource-group rg-jam --server jam-sql-espejo
      Después, la consulta 1 conectada a jam-fog.database.windows.net debe
      mostrar el servidor jam-sql-espejo como PRIMARY. Para regresar:
         az sql failover-group set-primary --name jam-fog --resource-group rg-jam --server jam-sql-principal
*/

/* 5. Contenido del espejo por fragmento (después de "Copiar al espejo"):
      debe coincidir con lo que tiene cada sucursal. */
SELECT id_store, COUNT(*) AS inventario, SUM(stock) AS piezas
FROM dbo.inventory
GROUP BY id_store;

SELECT id_store, COUNT(*) AS pedidos, SUM(total) AS vendido
FROM dbo.customer_order
GROUP BY id_store;

SELECT table_name, rows_copied, watermark, synced_at
FROM dbo.mirror_sync
ORDER BY table_name;
GO
