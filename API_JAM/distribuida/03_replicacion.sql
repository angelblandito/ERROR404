/* =====================================================================
   JAM - Base de datos distribuida
   03. Replicación transaccional
   ---------------------------------------------------------------------
   Requisitos:
     - SQL Server 2022 Developer (o Standard/Enterprise). Express no puede
       publicar ni tiene SQL Server Agent.
     - Servicio "SQL Server Agent" iniciado.
     - Scripts 01 y 02 ejecutados en la misma instancia.
     - Para la suscripción a la nube: base JAM_cloud vacía creada en Azure SQL
       y regla de firewall que permita la IP pública de esta máquina.

   Publicaciones (publicador y distribuidor = esta instancia, nodo central):
     pub_catalogo  Tablas de catálogo -> JAM_tijuana, JAM_cdmx, JAM_online
                   Cada sucursal tiene una copia local del catálogo, así puede
                   vender aunque el nodo central no responda.
     pub_completa  Todas las tablas de JAM_db -> Azure SQL (JAM_cloud)
                   Opcional: se deja sin suscriptor (@azure_servidor vacío)
                   porque la API ya copia JAM_db a JAM_cloud; las dos copias
                   a la vez chocarían.

   Edite las variables marcadas con >>> antes de ejecutar.
   ===================================================================== */

IF CAST(SERVERPROPERTY('EngineEdition') AS INT) = 4
BEGIN
    RAISERROR (N'Esta instancia es SQL Server Express: la replicación requiere la edición Developer.', 16, 1);
    SET NOEXEC ON;
END;
GO

/* ---------------------------------------------------------------------
   1. Distribuidor local
   --------------------------------------------------------------------- */
USE master;
GO

DECLARE @servidor SYSNAME = @@SERVERNAME;
DECLARE @password_distribuidor SYSNAME = N'';   -- Llenar: contraseña del distribuidor (borrarla antes de subir a GitHub)

IF NOT EXISTS (SELECT 1 FROM sys.databases WHERE is_distributor = 1)
BEGIN
    IF LEN(@password_distribuidor) = 0
        THROW 50021, N'Escriba la contraseña del distribuidor en @password_distribuidor y vuelva a ejecutar el script.', 1;

    EXEC sp_adddistributor @distributor = @servidor, @password = @password_distribuidor;
    EXEC sp_adddistributiondb @database = N'distribution', @security_mode = 1;
    EXEC sp_adddistpublisher @publisher = @servidor, @distribution_db = N'distribution', @security_mode = 1;
END;
GO

/* ---------------------------------------------------------------------
   2. Habilitar JAM_db para publicar
   --------------------------------------------------------------------- */
USE JAM_db;
GO

EXEC sp_replicationdboption @dbname = N'JAM_db', @optname = N'publish', @value = N'true';
GO

/* ---------------------------------------------------------------------
   3. Publicaciones y artículos
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_crear_publicacion
    @publicacion SYSNAME,
    @descripcion NVARCHAR(255),
    @tablas      NVARCHAR(MAX)          -- lista separada por comas; '*' = todas las tablas de dbo
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM dbo.syspublications WHERE name = @publicacion)
    BEGIN
        EXEC sp_addpublication
            @publication     = @publicacion,
            @description     = @descripcion,
            @status          = N'active',
            @repl_freq       = N'continuous',
            @allow_push      = N'true',
            @allow_pull      = N'false',
            @independent_agent = N'true',
            @immediate_sync  = N'true';

        EXEC sp_addpublication_snapshot
            @publication = @publicacion,
            @publisher_security_mode = 1;
    END;

    DECLARE @tabla SYSNAME, @tiene_identity BIT, @opcion_identity NVARCHAR(10);

    DECLARE articulos CURSOR LOCAL STATIC READ_ONLY FOR
        SELECT t.name,
               CASE WHEN EXISTS (SELECT 1 FROM sys.identity_columns AS ic WHERE ic.object_id = t.object_id) THEN 1 ELSE 0 END
        FROM sys.tables AS t
        WHERE t.schema_id = SCHEMA_ID(N'dbo')
          AND t.is_ms_shipped = 0
          AND (@tablas = N'*' OR t.name IN (SELECT LTRIM(RTRIM(value)) FROM STRING_SPLIT(@tablas, N',')))
          /* Credenciales y sesiones nunca salen del nodo central */
          AND t.name NOT IN (N'customer_credential', N'customer_session')
          AND NOT EXISTS (
                SELECT 1
                FROM dbo.sysarticles AS a
                JOIN dbo.syspublications AS p ON p.pubid = a.pubid
                WHERE p.name = @publicacion AND a.name = t.name);

    OPEN articulos;
    FETCH NEXT FROM articulos INTO @tabla, @tiene_identity;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @opcion_identity = CASE WHEN @tiene_identity = 1 THEN N'manual' ELSE N'none' END;

        EXEC sp_addarticle
            @publication       = @publicacion,
            @article           = @tabla,
            @source_owner      = N'dbo',
            @source_object     = @tabla,
            @destination_table = @tabla,
            @destination_owner = N'dbo',
            @type              = N'logbased',
            @pre_creation_cmd  = N'drop',
            @identityrangemanagementoption = @opcion_identity;

        FETCH NEXT FROM articulos INTO @tabla, @tiene_identity;
    END;

    CLOSE articulos;
    DEALLOCATE articulos;
END;
GO

EXEC dist.sp_crear_publicacion
    @publicacion = N'pub_catalogo',
    @descripcion = N'Catálogo JAM replicado a las sucursales y a la tienda en línea',
    @tablas      = N'role, store_type, category, size, color, loyalty_level, account_tier, absence_type, state, season_theme, store, store_config, product, product_variant, promotion';
GO

EXEC dist.sp_crear_publicacion
    @publicacion = N'pub_completa',
    @descripcion = N'Copia completa de JAM_db hacia Azure SQL',
    @tablas      = N'*';
GO

/* ---------------------------------------------------------------------
   4. Suscripciones push
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_crear_suscripcion
    @publicacion        SYSNAME,
    @suscriptor         SYSNAME,
    @base_destino       SYSNAME,
    @usuario            SYSNAME = NULL,         -- NULL = autenticación de Windows
    @password           SYSNAME = NULL
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM dbo.syssubscriptions AS s
        JOIN dbo.sysarticles AS a ON a.artid = s.artid
        JOIN dbo.syspublications AS p ON p.pubid = a.pubid
        WHERE p.name = @publicacion AND s.srvname = @suscriptor AND s.dest_db = @base_destino)
        RETURN;

    EXEC sp_addsubscription
        @publication       = @publicacion,
        @subscriber        = @suscriptor,
        @destination_db    = @base_destino,
        @subscription_type = N'Push',
        @sync_type         = N'automatic',
        @article           = N'all',
        @update_mode       = N'read only',
        @subscriber_type   = 0;

    IF @usuario IS NULL
        EXEC sp_addpushsubscription_agent
            @publication              = @publicacion,
            @subscriber               = @suscriptor,
            @subscriber_db            = @base_destino,
            @subscriber_security_mode = 1,
            @frequency_type           = 64;
    ELSE
        EXEC sp_addpushsubscription_agent
            @publication              = @publicacion,
            @subscriber               = @suscriptor,
            @subscriber_db            = @base_destino,
            @subscriber_security_mode = 0,
            @subscriber_login         = @usuario,
            @subscriber_password      = @password,
            @frequency_type           = 64;
END;
GO

DECLARE @local SYSNAME = @@SERVERNAME;

EXEC dist.sp_crear_suscripcion @publicacion = N'pub_catalogo', @suscriptor = @local, @base_destino = N'JAM_tijuana';
EXEC dist.sp_crear_suscripcion @publicacion = N'pub_catalogo', @suscriptor = @local, @base_destino = N'JAM_cdmx';
EXEC dist.sp_crear_suscripcion @publicacion = N'pub_catalogo', @suscriptor = @local, @base_destino = N'JAM_online';
GO

/* Suscriptor en Azure. Se omite mientras @azure_servidor esté vacío.
   Se recomienda usar el listener del grupo de conmutación por error
   (ver 04_espejo_azure.sql) para que la réplica siga funcionando
   después de un failover. */
DECLARE @azure_servidor SYSNAME = N'';                    -- Llenar: p. ej. N'jam-fog.database.windows.net'
DECLARE @azure_base     SYSNAME = N'JAM_cloud';
DECLARE @azure_usuario  SYSNAME = N'jamadmin';            -- Llenar: usuario administrador de Azure SQL
DECLARE @azure_password SYSNAME = N'';                    -- Llenar: contraseña (no subir a GitHub)

IF @azure_servidor <> N''
    EXEC dist.sp_crear_suscripcion
        @publicacion  = N'pub_completa',
        @suscriptor   = @azure_servidor,
        @base_destino = @azure_base,
        @usuario      = @azure_usuario,
        @password     = @azure_password;
ELSE
    PRINT N'Suscripción a Azure omitida: falta configurar @azure_servidor.';
GO

/* ---------------------------------------------------------------------
   5. Generar las instantáneas iniciales
   --------------------------------------------------------------------- */
EXEC sp_startpublication_snapshot @publication = N'pub_catalogo';
EXEC sp_startpublication_snapshot @publication = N'pub_completa';
GO

/* ---------------------------------------------------------------------
   6. Estado (esperar 1-2 minutos después de la instantánea)
   --------------------------------------------------------------------- */
SELECT p.name AS publicacion, s.srvname AS suscriptor, s.dest_db AS base_destino, COUNT(*) AS articulos
FROM dbo.syssubscriptions AS s
JOIN dbo.sysarticles AS a ON a.artid = s.artid
JOIN dbo.syspublications AS p ON p.pubid = a.pubid
WHERE s.srvid >= 0
GROUP BY p.name, s.srvname, s.dest_db
ORDER BY p.name, s.dest_db;

SELECT TOP (20) a.name AS agente, h.time AS fecha, h.comments AS mensaje
FROM distribution.dbo.MSdistribution_history AS h
JOIN distribution.dbo.MSdistribution_agents AS a ON a.id = h.agent_id
ORDER BY h.time DESC;
GO

SET NOEXEC OFF;
GO
