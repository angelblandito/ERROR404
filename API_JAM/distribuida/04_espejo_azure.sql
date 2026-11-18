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

   Los servidores, la base y el grupo se crean con Azure CLI o el portal
   (ver docs/GUIA_DISTRIBUIDA_Y_NUBE.md). Este archivo contiene las
   consultas para comprobar el espejo; se ejecutan conectado a la base
   JAM_cloud a través de jam-fog.database.windows.net.
   ===================================================================== */

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
