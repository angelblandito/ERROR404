/* =====================================================================
   JAM - Base de datos distribuida
   14. Práctica de la Unidad 4: transacciones distribuidas y alta
       disponibilidad, aplicada a JAM Sport en SQL Server
   ---------------------------------------------------------------------
   Requisitos: 13_manejo_transacciones.sql ejecutado.
   Se ejecuta en SSMS, conectado a la instancia con un usuario
   administrador (no con jam_api).

   Cómo usarla:
     - Las partes marcadas "UNA SESIÓN" se pueden ejecutar todas de corrido
       con F5: dejan las existencias como estaban (lo que mueven lo
       regresan o lo deshacen con ROLLBACK).
     - Las partes marcadas "DOS SESIONES" están comentadas: se copian a dos
       ventanas de consulta (New Query) y se ejecutan paso a paso, como
       dice cada bloque.
     - Las partes marcadas "SOLO EN LA LAPTOP" apagan una base: no se
       hacen en la VM porque la tienda está en línea.

   Etapas (equivalentes a las de la práctica de la unidad):
     A. Propiedades ACID
     B. Control de concurrencia: 2PL, MVCC, interbloqueo y su prevención
     C. Transacción distribuida con confirmación en dos fases (2PC)
     D. Replicación y espejeo
     E. Puntos de verificación y bitácora
   Al final viene la tabla de evidencias.
   ===================================================================== */

USE JAM_db;
GO
SET NOCOUNT ON;
SET XACT_ABORT OFF;   -- en la práctica queremos ver cada error sin cortar el script
GO

/* ---------------------------------------------------------------------
   Preparación (UNA SESIÓN): una talla y color con existencias en
   Tijuana (1) y en CDMX (2). Su id queda guardado en la sesión.
   --------------------------------------------------------------------- */
DECLARE @v INT = (
    SELECT TOP (1) a.id_variant
    FROM dbo.inventory AS a
    JOIN dbo.inventory AS b ON b.id_variant = a.id_variant AND b.id_store = 2 AND b.deleted = 0 AND b.stock >= 5
    WHERE a.id_store = 1 AND a.deleted = 0 AND a.stock >= 5
    ORDER BY a.id_variant);

EXEC sys.sp_set_session_context @key = N'v', @value = @v;

PRINT CONCAT(N'Variante de prueba: ', @v, N'. Úsala en los bloques de DOS SESIONES.');

SELECT 'antes' AS momento, i.id_store, i.id_variant, i.stock
FROM dbo.inventory AS i
WHERE i.id_variant = @v AND i.id_store IN (1, 2)
ORDER BY i.id_store;
GO

/* =====================================================================
   ETAPA A. Propiedades ACID
   ===================================================================== */

/* A1. Atomicidad (UNA SESIÓN)
   Una transacción que escribe en la central y en el fragmento de
   Tijuana; antes del COMMIT se simula un error y se hace ROLLBACK.
   Ninguna de las dos bases debe quedar cambiada. */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
DECLARE @antes_central INT = (SELECT stock FROM JAM_db.dbo.inventory WHERE id_store = 1 AND id_variant = @v);
DECLARE @antes_tijuana INT = (SELECT stock FROM JAM_tijuana.dbo.inventory WHERE id_store = 1 AND id_variant = @v);

BEGIN TRANSACTION;
    UPDATE JAM_db.dbo.inventory      SET stock = stock - 1 WHERE id_store = 1 AND id_variant = @v;
    UPDATE JAM_tijuana.dbo.inventory SET stock = stock - 1 WHERE id_store = 1 AND id_variant = @v;
    -- error simulado antes de confirmar:
ROLLBACK TRANSACTION;

SELECT 'A1 atomicidad' AS prueba,
       @antes_central AS central_antes,
       (SELECT stock FROM JAM_db.dbo.inventory WHERE id_store = 1 AND id_variant = @v) AS central_despues,
       @antes_tijuana AS tijuana_antes,
       (SELECT stock FROM JAM_tijuana.dbo.inventory WHERE id_store = 1 AND id_variant = @v) AS tijuana_despues;
-- Esperado: antes = después en las dos bases.
GO

/* A1 (b). Atomicidad con el procedimiento: un traspaso imposible
   (más piezas de las que hay) se aborta completo y queda en la bitácora. */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
BEGIN TRY
    EXEC dist.sp_traspasar_inventario @id_variant = @v, @desde_store = 1, @hacia_store = 2, @cantidad = 9999;
END TRY
BEGIN CATCH
    SELECT 'A1 traspaso imposible' AS prueba, ERROR_NUMBER() AS error, ERROR_MESSAGE() AS mensaje;
END CATCH;

SELECT TOP (1) 'A1 bitácora' AS prueba, id_transaction, state, attempts, error_number, error_message
FROM dist.transaction_log
ORDER BY id_transaction DESC;
-- Esperado: error 50125 y la transacción registrada como "abortada".
GO

/* A2. Consistencia (UNA SESIÓN)
   Las restricciones se revisan en cada nodo:
   - en la central, el CHECK ck_inventory_stock no deja existencias negativas;
   - en el fragmento de Tijuana, el CHECK ck_inventory_fragment no deja
     guardar un renglón de otra sucursal (el predicado id_store = 1). */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);

BEGIN TRY
    BEGIN TRANSACTION;
        UPDATE JAM_db.dbo.inventory SET stock = -5 WHERE id_store = 1 AND id_variant = @v;
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    SELECT 'A2 existencias negativas' AS prueba, ERROR_NUMBER() AS error, ERROR_MESSAGE() AS mensaje;
END CATCH;

BEGIN TRY
    BEGIN TRANSACTION;
        INSERT INTO JAM_tijuana.dbo.inventory (id_inventory, id_store, id_variant, stock, min_stock, source_node, updated_at, deleted)
        VALUES (-1, 2, @v, 1, 0, 'practica', SYSUTCDATETIME(), 0);
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    SELECT 'A2 renglón de otra sucursal' AS prueba, ERROR_NUMBER() AS error, ERROR_MESSAGE() AS mensaje;
END CATCH;
-- Esperado: error 547 (CHECK) en los dos casos y nada guardado.
GO

/* A3. Aislamiento (DOS SESIONES)
   Con READ_COMMITTED_SNAPSHOT (script 13) las lecturas no esperan a las
   escrituras: leen la última versión confirmada (MVCC).

   -- SESIÓN 1 ----------------------------------------------------------
   USE JAM_db;
   BEGIN TRANSACTION;
   UPDATE dbo.inventory SET stock = stock + 100 WHERE id_store = 1 AND id_variant = <v>;
   -- no confirmar todavía

   -- SESIÓN 2 ----------------------------------------------------------
   USE JAM_db;
   SELECT stock FROM dbo.inventory WHERE id_store = 1 AND id_variant = <v>;
   -- Responde al instante con el valor SIN los +100: no hay lectura sucia
   -- y no espera (versión confirmada).
   SELECT stock FROM dbo.inventory WITH (READCOMMITTEDLOCK) WHERE id_store = 1 AND id_variant = <v>;
   -- Esta lectura pide bloqueo compartido (S): ESPERA a la sesión 1 (2PL).

   -- SESIÓN 1 ----------------------------------------------------------
   ROLLBACK TRANSACTION;   -- la sesión 2 termina de leer el valor original

   Lectura repetible con SNAPSHOT:
   -- SESIÓN 1 ----------------------------------------------------------
   SET TRANSACTION ISOLATION LEVEL SNAPSHOT;
   BEGIN TRANSACTION;
   SELECT stock FROM dbo.inventory WHERE id_store = 2 AND id_variant = <v>;   -- anotar el valor
   -- SESIÓN 2 ----------------------------------------------------------
   UPDATE dbo.inventory SET stock = stock + 1 WHERE id_store = 2 AND id_variant = <v>;   -- se confirma solo
   -- SESIÓN 1 ----------------------------------------------------------
   SELECT stock FROM dbo.inventory WHERE id_store = 2 AND id_variant = <v>;   -- el MISMO valor de antes
   COMMIT TRANSACTION;
   SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
   -- SESIÓN 2 ----------------------------------------------------------
   UPDATE dbo.inventory SET stock = stock - 1 WHERE id_store = 2 AND id_variant = <v>;   -- dejarlo como estaba
*/

/* A4. Durabilidad (UNA SESIÓN + reinicio opcional, SOLO EN LA LAPTOP)
   Un traspaso confirmado de 1 pieza de Tijuana a CDMX y otro de regreso.
   Para comprobar que sobrevive a un fallo, después del primero reiniciar
   el servicio (PowerShell como administrador):
       Restart-Service 'MSSQL$SQLEXPRESS'   # laptop con SQL Express
       Restart-Service MSSQLSERVER          # instancia predeterminada
   y volver a consultar: el cambio sigue ahí porque se escribió en la
   bitácora (WAL) antes del COMMIT. */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
EXEC dist.sp_traspasar_inventario @id_variant = @v, @desde_store = 1, @hacia_store = 2, @cantidad = 1;
EXEC dist.sp_traspasar_inventario @id_variant = @v, @desde_store = 2, @hacia_store = 1, @cantidad = 1;
GO

/* =====================================================================
   ETAPA B. Control de concurrencia
   ===================================================================== */

/* B1. Bloqueo en dos fases (DOS SESIONES + una tercera para mirar)

   -- SESIÓN 1 ----------------------------------------------------------
   USE JAM_db;
   BEGIN TRANSACTION;
   SELECT * FROM dbo.inventory WITH (UPDLOCK, HOLDLOCK) WHERE id_store = 1 AND id_variant = <v>;
   -- no confirmar todavía (fase de crecimiento)

   -- SESIÓN 2 ----------------------------------------------------------
   USE JAM_db;
   BEGIN TRANSACTION;
   SELECT * FROM dbo.inventory WITH (UPDLOCK, HOLDLOCK) WHERE id_store = 1 AND id_variant = <v>;
   -- queda en espera

   -- SESIÓN 3 (mirar) --------------------------------------------------
   SELECT * FROM JAM_db.dist.vw_grafo_espera;          -- arco sesión 2 -> sesión 1
   SELECT * FROM JAM_db.dist.vw_transacciones_activas; -- las dos transacciones "activa"
   SELECT request_session_id AS sesion, resource_type, request_mode AS modo, request_status AS estado
   FROM sys.dm_tran_locks
   WHERE resource_database_id = DB_ID(N'JAM_db') AND resource_type IN (N'KEY', N'RID', N'PAGE', N'OBJECT');
   -- modo U (actualización) GRANT para la sesión 1 y WAIT para la sesión 2

   -- SESIÓN 1 ----------------------------------------------------------
   COMMIT TRANSACTION;   -- libera los bloqueos (fase de decrecimiento)
   -- SESIÓN 2 ----------------------------------------------------------
   COMMIT TRANSACTION;   -- ya obtuvo el bloqueo y termina
*/

/* B2. Interbloqueo deliberado (DOS SESIONES)
   Cada sesión bloquea primero una sucursal distinta y luego pide la otra:
   espera circular. SQL Server la detecta, elige una víctima y le devuelve
   el error 1205.

   -- SESIÓN 1 ----------------------------------------------------------
   USE JAM_db;
   BEGIN TRANSACTION;
   UPDATE dbo.inventory SET stock = stock + 0 WHERE id_store = 1 AND id_variant = <v>;
   WAITFOR DELAY '00:00:05';
   UPDATE dbo.inventory SET stock = stock + 0 WHERE id_store = 2 AND id_variant = <v>;
   COMMIT TRANSACTION;

   -- SESIÓN 2 (iniciarla dentro de esos 5 segundos) --------------------
   USE JAM_db;
   BEGIN TRANSACTION;
   UPDATE dbo.inventory SET stock = stock + 0 WHERE id_store = 2 AND id_variant = <v>;
   WAITFOR DELAY '00:00:05';
   UPDATE dbo.inventory SET stock = stock + 0 WHERE id_store = 1 AND id_variant = <v>;
   COMMIT TRANSACTION;

   Resultado: una de las dos recibe
   "Msg 1205 ... was deadlocked ... and has been chosen as the deadlock victim".
   La evidencia (el grafo del interbloqueo) queda en la sesión de eventos
   system_health; ver la consulta B2 de abajo.
*/

/* Evidencia B2 (UNA SESIÓN): últimos interbloqueos detectados */
SELECT TOP (5)
       x.value('(@timestamp)[1]', 'datetime2') AS momento_utc,
       x.query('.')                            AS grafo_del_interbloqueo
FROM (
    SELECT CAST(target_data AS XML) AS datos
    FROM sys.dm_xe_session_targets AS t
    JOIN sys.dm_xe_sessions AS s ON s.address = t.event_session_address
    WHERE s.name = N'system_health' AND t.target_name = N'ring_buffer'
) AS r
CROSS APPLY r.datos.nodes('RingBufferTarget/event[@name="xml_deadlock_report"]') AS e(x)
ORDER BY momento_utc DESC;
-- Clic en el XML: muestra los dos procesos, el recurso que pedía cada uno y la víctima.
GO

/* B3. Prevención: orden fijo de bloqueo (DOS SESIONES)
   Dos traspasos en sentidos contrarios a la vez. dist.sp_traspasar_inventario
   bloquea siempre primero el id_store menor, así que no se forma el ciclo:
   el segundo simplemente espera al primero y los dos se confirman.

   -- SESIÓN 1 ----------------------------------------------------------
   EXEC JAM_db.dist.sp_traspasar_inventario @id_variant = <v>, @desde_store = 1, @hacia_store = 2,
        @cantidad = 1, @pausa_ms = 5000;

   -- SESIÓN 2 (dentro de esos 5 segundos) ------------------------------
   EXEC JAM_db.dist.sp_traspasar_inventario @id_variant = <v>, @desde_store = 2, @hacia_store = 1,
        @cantidad = 1, @pausa_ms = 5000;

   Resultado: los dos responden "confirmada" con intentos = 1 y las
   existencias quedan igual que al inicio (se fue 1 y regresó 1).
   Comparar con B2, donde el orden distinto sí provocó el interbloqueo.
*/

/* =====================================================================
   ETAPA C. Transacción distribuida y confirmación en dos fases (2PC)
   ===================================================================== */

/* C1 (UNA SESIÓN): el traspaso escribe en tres bases en una sola
   transacción. En la bitácora de SQL Server se ven los registros de
   preparación (LOP_PREP_XACT) de la confirmación en dos fases que SQL
   Server hace entre las bases de la misma instancia.
   fn_dblog lee la bitácora activa; es una función interna, solo para
   consultar. */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
CHECKPOINT;   -- deja la bitácora corta para que se vea solo lo nuevo
EXEC dist.sp_traspasar_inventario @id_variant = @v, @desde_store = 1, @hacia_store = 2, @cantidad = 1;

SELECT TOP (30) [Current LSN] AS lsn, Operation AS operacion, [Transaction ID] AS id_transaccion,
       AllocUnitName AS objeto
FROM fn_dblog(NULL, NULL)
WHERE Operation IN (N'LOP_BEGIN_XACT', N'LOP_PREP_XACT', N'LOP_COMMIT_XACT', N'LOP_ABORT_XACT', N'LOP_MODIFY_ROW')
ORDER BY [Current LSN] DESC;
-- Esperado: BEGIN_XACT, MODIFY_ROW sobre dbo.inventory, PREP_XACT y COMMIT_XACT.
GO

USE JAM_tijuana;
GO
SELECT TOP (10) [Current LSN] AS lsn, Operation AS operacion, [Transaction ID] AS id_transaccion
FROM fn_dblog(NULL, NULL)
WHERE Operation IN (N'LOP_BEGIN_XACT', N'LOP_PREP_XACT', N'LOP_COMMIT_XACT')
ORDER BY [Current LSN] DESC;
-- El participante (fragmento de Tijuana) también registra su PREP_XACT y su COMMIT_XACT.
GO
USE JAM_db;
GO

/* C1 (b): se regresa la pieza para dejar todo como estaba */
DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
EXEC dist.sp_traspasar_inventario @id_variant = @v, @desde_store = 2, @hacia_store = 1, @cantidad = 1;
GO

/* C2. Transacción "preparada" vista desde afuera (DOS SESIONES)
   -- SESIÓN 1 ----------------------------------------------------------
   EXEC JAM_db.dist.sp_traspasar_inventario @id_variant = <v>, @desde_store = 1, @hacia_store = 2,
        @cantidad = 1, @pausa_ms = 8000;
   -- SESIÓN 2 (durante esos 8 segundos) --------------------------------
   SELECT * FROM JAM_db.dist.vw_transacciones_activas;
   SELECT * FROM JAM_db.dist.transaction_log WHERE state = 'activa';
   -- La transacción aparece "activa" en las dos vistas. Al terminar, la
   -- bitácora la muestra "confirmada". Regresar la pieza con @desde_store = 2, @hacia_store = 1.
*/

/* C3. Falla de un participante (SOLO EN LA LAPTOP)
   ALTER DATABASE JAM_cdmx SET OFFLINE WITH ROLLBACK IMMEDIATE;
   EXEC JAM_db.dist.sp_traspasar_inventario @id_variant = <v>, @desde_store = 1, @hacia_store = 2, @cantidad = 1;
   -- Error 50124: la transacción se aborta sin tocar ninguna base (todo o nada).
   SELECT TOP (1) * FROM JAM_db.dist.transaction_log ORDER BY id_transaction DESC;   -- "abortada"
   ALTER DATABASE JAM_cdmx SET ONLINE;
   EXEC JAM_db.dist.sp_sincronizar_nodos;   -- pone al día el nodo que volvió
*/

/* =====================================================================
   ETAPA D. Replicación y espejeo
   JAM Sport tiene tres niveles de copia:
     1. Replicación transaccional (pub_catalogo): JAM_db -> JAM_tijuana,
        JAM_cdmx y JAM_online. Es ASÍNCRONA: la central confirma y después
        el agente de distribución lleva los cambios.
     2. Espejo en Azure SQL: la API copia JAM_db a JAM_cloud y el grupo de
        conmutación jam-fog-jp3 lo replica a un segundo servidor (también
        asíncrono, con conmutación automática).
     3. Respaldo completo cada 10 minutos a la VM de respaldo, con
        Traffic Manager para cambiar de servidor si la principal cae.
   ===================================================================== */

/* D1 (UNA SESIÓN, en la VM principal): latencia real de la replicación
   con un "tracer token" (marca que viaja por la replicación). */
IF DB_ID(N'distribution') IS NOT NULL
   AND EXISTS (SELECT 1 FROM sys.databases WHERE name = N'JAM_db' AND is_published = 1)
BEGIN
    DECLARE @token INT;
    EXEC sys.sp_posttracertoken @publication = N'pub_catalogo', @tracer_token_id = @token OUTPUT;
    WAITFOR DELAY '00:00:10';
    EXEC sys.sp_helptracertokenhistory @publication = N'pub_catalogo', @tracer_id = @token;
END
ELSE
    PRINT N'D1: esta instancia no publica la replicación (normal en la laptop con SQL Express).';
-- Esperado: distributor_latency y subscriber_latency de pocos segundos por suscriptor.
GO

/* D2. Espejo en Azure SQL: ejecutar CONECTADO A JAM_cloud en
   jam-sql-principal-jp31.database.windows.net (no a la VM):

   SELECT partner_server, partner_database, role_desc, replication_state_desc,
          replication_lag_sec, last_replication
   FROM sys.dm_geo_replication_link_status;
   -- role_desc = PRIMARY, replication_state_desc = CATCH_UP y replication_lag_sec cercano a 0.

   Y en la central, la última copia que hizo la API:
   SELECT * FROM JAM_db.dbo.mirror_sync ORDER BY 1;
*/

/* D3. Conmutación automática del servidor (ya probada el 26 nov 2026):
   Stop-Service Apache2.4 en jam-vm-cl -> Traffic Manager marca la
   principal "Degraded" y manda el tráfico a jam-vm-cl2 -> Start-Service
   Apache2.4 -> las dos "Online". */

/* =====================================================================
   ETAPA E. Puntos de verificación y bitácora
   ===================================================================== */

/* E1 (UNA SESIÓN): punto de verificación global coordinado por la central */
EXEC dist.sp_punto_verificacion_global;
-- Esperado: estado_global = confirmada y un renglón "hecho" con su LSN por nodo.
GO

/* E2 (UNA SESIÓN): qué tanto log habría que procesar si el servidor fallara ahora */
SELECT DB_NAME(s.database_id)             AS base,
       s.recovery_model                   AS modelo_recuperacion,
       s.checkpoint_lsn,
       s.log_since_last_checkpoint_mb     AS log_desde_checkpoint_mb,
       s.total_log_size_mb                AS tamano_log_mb,
       s.active_log_size_mb               AS log_activo_mb
FROM sys.databases AS d
CROSS APPLY sys.dm_db_log_stats(d.database_id) AS s
WHERE d.name IN (N'JAM_db', N'JAM_tijuana', N'JAM_cdmx', N'JAM_online');
GO

/* E3. Recuperación al reiniciar (ARIES): SQL Server la hace en tres fases
   (análisis, rehacer y deshacer). Después de un reinicio se ven en el log
   de errores del servidor: */
EXEC sys.xp_readerrorlog 0, 1, N'Recovery';
-- Mensajes como "Recovery of database 'JAM_db' ... Phase 1 of 3 / 2 of 3 / 3 of 3"
-- y "Recovery completed for database JAM_db".
GO

/* =====================================================================
   Resumen de la sesión: bitácora de transacciones
   ===================================================================== */
SELECT TOP (20) id_transaction, tx_type, state, participants, attempts, error_number, started_at, ended_at
FROM dist.transaction_log
ORDER BY id_transaction DESC;

DECLARE @v INT = CAST(SESSION_CONTEXT(N'v') AS INT);
SELECT 'después' AS momento, i.id_store, i.id_variant, i.stock
FROM dbo.inventory AS i
WHERE i.id_variant = @v AND i.id_store IN (1, 2)
ORDER BY i.id_store;
-- Las existencias deben ser las mismas que en "antes".
GO

/* =====================================================================
   Evidencias de la práctica
   ---------------------------------------------------------------------
   Etapa  Concepto                        Evidencia (captura)
   A      Atomicidad                      A1: antes = después; traspaso imposible "abortada"
   A      Consistencia                    A2: error 547 de los dos CHECK
   A      Aislamiento                     A3: lectura sin espera (MVCC) y lectura que espera (2PL);
                                          mismo valor dos veces con SNAPSHOT
   A      Durabilidad                     A4: traspaso confirmado sigue después del reinicio
   B      2PL                             B1: dist.vw_grafo_espera y sys.dm_tran_locks (U GRANT / WAIT)
   B      Interbloqueo                    B2: Msg 1205 y el XML de system_health
   B      Prevención                      B3: dos traspasos cruzados, los dos "confirmada" con 1 intento
   C      2PC                             C1: LOP_PREP_XACT y LOP_COMMIT_XACT en JAM_db y JAM_tijuana
   C      Falla de participante           C3: error 50124 y "abortada"
   D      Replicación asíncrona           D1: latencia del tracer token; Monitor de replicación
   D      Espejo y conmutación            D2: sys.dm_geo_replication_link_status; D3: Traffic Manager
   E      Punto de verificación global    E1: un "hecho" con LSN por nodo
   E      Bitácora y recuperación         E2: log desde el checkpoint; E3: fases de recuperación
   ===================================================================== */
