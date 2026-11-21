/* =====================================================================
   JAM - Base de datos distribuida
   02. Fragmentación vertical de customer
   ---------------------------------------------------------------------
   Requisitos: 01_fragmentacion_horizontal.sql ejecutado.

   La relación customer se divide por columnas en dos fragmentos.
   Ambos conservan la llave id_customer para poder reconstruirla con JOIN.

   Fragmento V1  customer_profile  -> JAM_online (nodo de la tienda en línea)
       Datos de perfil que necesita la tienda: nombre, nivel de lealtad,
       tipo de cuenta, antigüedad, sucursal de registro y si acepta publicidad.

   Fragmento V2  customer_private  -> JAM_db, esquema dist (nodo central)
       Datos personales sensibles: fecha de nacimiento, sexo, teléfono,
       correo y domicilio. Se quedan en el nodo central, que es el de
       acceso restringido.
   ===================================================================== */

USE JAM_online;
GO

IF OBJECT_ID(N'dbo.customer_profile', N'U') IS NULL
CREATE TABLE dbo.customer_profile (
    id_customer         INT          NOT NULL,
    id_loyalty_level    TINYINT      NOT NULL,
    id_account_tier     TINYINT      NOT NULL,
    id_store            INT          NOT NULL,
    first_name          NVARCHAR(50)  NOT NULL,
    last_name_paternal  NVARCHAR(50)  NOT NULL,
    last_name_maternal  NVARCHAR(50)  NULL,
    accepts_marketing   BIT          NOT NULL,
    customer_since      DATE         NOT NULL,
    source_node         VARCHAR(20)  NOT NULL,
    updated_at          DATETIME2(3)     NOT NULL,
    deleted             BIT          NOT NULL,
    CONSTRAINT pk_customer_profile PRIMARY KEY (id_customer)
);
GO

/* Si el fragmento ya existía de una versión anterior, se agregan las columnas
   nuevas; el contenido se vuelve a copiar completo en cada sincronización. */
IF COL_LENGTH(N'dbo.customer_profile', N'id_account_tier') IS NULL
    ALTER TABLE dbo.customer_profile ADD
        id_account_tier TINYINT NOT NULL CONSTRAINT df_customer_profile_account_tier DEFAULT 1,
        customer_since  DATE    NOT NULL CONSTRAINT df_customer_profile_since DEFAULT '2000-01-01';
GO

USE JAM_db;
GO

IF OBJECT_ID(N'dist.customer_private', N'U') IS NULL
CREATE TABLE dist.customer_private (
    id_customer   INT           NOT NULL,
    id_address    INT           NULL,
    birth_date    DATE          NOT NULL,
    sex           CHAR(1)       NOT NULL,
    phone         VARCHAR(15)   NOT NULL,
    email         VARCHAR(100)  NOT NULL,
    updated_at    DATETIME2(3)      NOT NULL,
    CONSTRAINT pk_customer_private PRIMARY KEY (id_customer),
    CONSTRAINT ck_customer_private_sex CHECK (sex IN ('M','F','X'))
);
GO

CREATE OR ALTER PROCEDURE dist.sp_refrescar_fragmentos_verticales
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    /* V2 vive en el nodo central: siempre se puede refrescar. */
    BEGIN TRANSACTION;

    DELETE FROM dist.customer_private;

    INSERT INTO dist.customer_private
        (id_customer, id_address, birth_date, sex, phone, email, updated_at)
    SELECT id_customer, id_address, birth_date, sex, phone, email, updated_at
    FROM dbo.customer;

    COMMIT TRANSACTION;

    /* V1 vive en el nodo de la tienda en línea. Si está fuera de línea el
       nodo queda pendiente y se pone al día en la siguiente sincronización. */
    DECLARE @db SYSNAME = (SELECT db_name FROM dist.node WHERE node_name = 'online');
    DECLARE @mensaje NVARCHAR(400);
    DECLARE @sql NVARCHAR(MAX) = REPLACE(N'
BEGIN TRANSACTION;

DELETE FROM {db}.dbo.customer_profile;

INSERT INTO {db}.dbo.customer_profile
    (id_customer, id_loyalty_level, id_account_tier, id_store, first_name, last_name_paternal, last_name_maternal,
     accepts_marketing, customer_since, source_node, updated_at, deleted)
SELECT id_customer, id_loyalty_level, id_account_tier, id_store, first_name, last_name_paternal, last_name_maternal,
       accepts_marketing, customer_since, source_node, updated_at, deleted
FROM JAM_db.dbo.customer;

COMMIT TRANSACTION;', N'{db}', QUOTENAME(@db));

    BEGIN TRY
        IF dist.fn_nodo_en_linea(@db) = 0
        BEGIN
            SET @mensaje = N'La base ' + @db + N' no está en línea (estado: '
                + ISNULL(CAST(DATABASEPROPERTYEX(@db, 'Status') AS NVARCHAR(30)), N'no existe') + N').';
            THROW 50100, @mensaje, 1;
        END;

        EXEC sys.sp_executesql @sql;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        UPDATE dist.node_sync
        SET pending = 1, last_error = LEFT(ERROR_MESSAGE(), 400)
        WHERE node_name = 'online';
    END CATCH;
END;
GO

/* ---------------------------------------------------------------------
   Reconstrucción: customer = customer_profile JOIN customer_private
   --------------------------------------------------------------------- */
CREATE OR ALTER VIEW dist.vw_customer_reconstruido AS
    SELECT
        p.id_customer,
        p.id_loyalty_level,
        p.id_account_tier,
        p.id_store,
        v.id_address,
        p.first_name,
        p.last_name_paternal,
        p.last_name_maternal,
        v.birth_date,
        v.sex,
        v.phone,
        v.email,
        p.accepts_marketing,
        p.customer_since,
        p.source_node,
        p.updated_at,
        p.deleted
    FROM JAM_online.dbo.customer_profile AS p
    JOIN dist.customer_private AS v ON v.id_customer = p.id_customer;
GO

/* ---------------------------------------------------------------------
   Sincronización completa de los nodos (horizontal + vertical)
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_sincronizar_nodos
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dist.sp_refrescar_fragmentos_horizontales;
    EXEC dist.sp_refrescar_fragmentos_verticales;

    SELECT GETDATE() AS synchronized_at,
           (SELECT COUNT(*) FROM dist.node_sync WHERE pending = 1) AS pending_nodes;
END;
GO

EXEC dist.sp_sincronizar_nodos;
GO

/* ---------------------------------------------------------------------
   Opcional: job de SQL Server Agent que sincroniza cada 5 minutos.
   SQL Server Express no tiene Agent (EngineEdition = 4), por eso se omite ahí.
   --------------------------------------------------------------------- */
IF CAST(SERVERPROPERTY('EngineEdition') AS INT) <> 4
   AND NOT EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = N'JAM - Sincronizar nodos')
BEGIN
    EXEC msdb.dbo.sp_add_job
        @job_name = N'JAM - Sincronizar nodos',
        @description = N'Refresca los fragmentos horizontales y verticales de JAM_db.';

    EXEC msdb.dbo.sp_add_jobstep
        @job_name = N'JAM - Sincronizar nodos',
        @step_name = N'sp_sincronizar_nodos',
        @subsystem = N'TSQL',
        @database_name = N'JAM_db',
        @command = N'EXEC dist.sp_sincronizar_nodos;';

    EXEC msdb.dbo.sp_add_jobschedule
        @job_name = N'JAM - Sincronizar nodos',
        @name = N'Cada 5 minutos',
        @freq_type = 4,
        @freq_interval = 1,
        @freq_subday_type = 4,
        @freq_subday_interval = 5;

    EXEC msdb.dbo.sp_add_jobserver @job_name = N'JAM - Sincronizar nodos';
END;
GO

/* Resultado esperado: los mismos clientes que dbo.customer */
SELECT * FROM dist.vw_customer_reconstruido;
GO
