/* =====================================================================
   JAM - Base de datos distribuida
   13. Manejo de transacciones (Unidad 4)
   ---------------------------------------------------------------------
   Requisitos: 01, 02, 06 y 07 ejecutados.

   Solo AGREGA objetos y cambia opciones de las bases; no borra datos.

   Qué aplica de la unidad:

   4.1 Transacciones distribuidas y propiedades ACID
       dist.sp_traspasar_inventario mueve existencias de una sucursal a
       otra en UNA transacción que escribe en tres bases: JAM_db (nodo
       central, coordinador) y los fragmentos de las dos sucursales
       (participantes). SQL Server confirma una transacción que abarca
       varias bases de la misma instancia con su confirmación en dos
       fases interna (2PC): se confirma en las tres bases o en ninguna.

   4.1 Estados de una transacción
       dist.transaction_log guarda cada transacción con su estado
       (activa -> confirmada | abortada), sus participantes, cuántos
       intentos necesitó y, si falló, el error.

   4.2 Control de concurrencia
       - 2PL estricto: el traspaso toma bloqueos de actualización
         (UPDLOCK, HOLDLOCK) sobre las dos filas antes de escribir y los
         conserva hasta el COMMIT (fase de crecimiento y fase de
         decrecimiento).
       - Prevención del interbloqueo: las dos filas se bloquean siempre
         en el mismo orden (primero el id_store menor), así dos traspasos
         cruzados no forman una espera circular.
       - Eliminación y recuperación: si SQL Server elige el traspaso como
         víctima de un interbloqueo (error 1205), la transacción se
         deshace y se reintenta hasta 3 veces con espera creciente
         (50, 100 y 200 ms). DEADLOCK_PRIORITY LOW hace que, si choca con
         la compra de un cliente, la víctima sea el traspaso.
       - MVCC: READ_COMMITTED_SNAPSHOT en las cuatro bases. Las lecturas
         usan la última versión confirmada y ya no esperan a las
         escrituras; las escrituras siguen usando bloqueos (2PL + MVCC).
       - dist.vw_grafo_espera: el grafo de espera (quién espera a quién).
       - dist.vw_transacciones_activas: transacciones abiertas con su
         estado y su nivel de aislamiento.

   4.3 Confiabilidad
       dist.sp_punto_verificacion_global: el nodo central pide un
       CHECKPOINT a cada nodo (CHECKPOINT-REQUEST), guarda el LSN de cada
       uno (CHECKPOINT-DONE) y registra el punto de verificación global
       solo si todos respondieron. Si un nodo no responde, el corte queda
       incompleto y así se registra.

   Permisos que agrega al usuario de la API (jam_api, en la VM):
     - db_backupoperator en cada nodo, para poder ejecutar CHECKPOINT;
     - VIEW SERVER STATE, para leer el grafo de espera, las transacciones
       activas y el LSN de la bitácora (solo lectura).
   En la laptop la API entra con la cuenta de Windows y no los necesita.

   ALTER DATABASE ... READ_COMMITTED_SNAPSHOT necesita unos segundos sin
   otras conexiones: WITH ROLLBACK IMMEDIATE cancela lo que la API tenga
   en curso en ese momento. Conviene ejecutarlo cuando nadie esté
   comprando.

   Se puede ejecutar varias veces.
   ===================================================================== */

USE master;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   1. MVCC: lecturas con versiones en las cuatro bases
   --------------------------------------------------------------------- */
DECLARE @db SYSNAME, @sql NVARCHAR(MAX);

DECLARE bases CURSOR LOCAL FAST_FORWARD FOR
    SELECT name
    FROM sys.databases
    WHERE name IN (N'JAM_db', N'JAM_tijuana', N'JAM_cdmx', N'JAM_online')
      AND state_desc = N'ONLINE';

OPEN bases;
FETCH NEXT FROM bases INTO @db;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF EXISTS (SELECT 1 FROM sys.databases WHERE name = @db AND is_read_committed_snapshot_on = 0)
    BEGIN
        SET @sql = N'ALTER DATABASE ' + QUOTENAME(@db) + N' SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;';
        EXEC sys.sp_executesql @sql;
    END;

    IF EXISTS (SELECT 1 FROM sys.databases WHERE name = @db AND snapshot_isolation_state = 0)
    BEGIN
        SET @sql = N'ALTER DATABASE ' + QUOTENAME(@db) + N' SET ALLOW_SNAPSHOT_ISOLATION ON;';
        EXEC sys.sp_executesql @sql;
    END;

    FETCH NEXT FROM bases INTO @db;
END;

CLOSE bases;
DEALLOCATE bases;
GO

/* ---------------------------------------------------------------------
   2. Permisos de jam_api (si existe; en la laptop no hace falta)
   --------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'jam_api')
    GRANT VIEW SERVER STATE TO jam_api;
GO

DECLARE @db SYSNAME, @sql NVARCHAR(MAX);

DECLARE bases CURSOR LOCAL FAST_FORWARD FOR
    SELECT name
    FROM sys.databases
    WHERE name IN (N'JAM_db', N'JAM_tijuana', N'JAM_cdmx', N'JAM_online')
      AND state_desc = N'ONLINE';

OPEN bases;
FETCH NEXT FROM bases INTO @db;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @sql = N'USE ' + QUOTENAME(@db) + N';
IF USER_ID(N''jam_api'') IS NOT NULL
    ALTER ROLE db_backupoperator ADD MEMBER jam_api;';
    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM bases INTO @db;
END;

CLOSE bases;
DEALLOCATE bases;
GO

USE JAM_db;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   3. Bitácora de transacciones y de puntos de verificación
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dist.transaction_log', N'U') IS NULL
CREATE TABLE dist.transaction_log (
    id_transaction  BIGINT        IDENTITY(1,1) NOT NULL,
    tx_type         VARCHAR(30)   NOT NULL,   -- traspaso_inventario | punto_verificacion
    state           VARCHAR(10)   NOT NULL,   -- activa | confirmada | abortada
    participants    VARCHAR(100)  NULL,       -- nodos que participan
    detail          NVARCHAR(400) NULL,
    attempts        TINYINT       NOT NULL CONSTRAINT df_transaction_log_attempts DEFAULT 0,
    error_number    INT           NULL,
    error_message   NVARCHAR(400) NULL,
    started_at      DATETIME2(3)  NOT NULL CONSTRAINT df_transaction_log_started DEFAULT SYSUTCDATETIME(),
    ended_at        DATETIME2(3)  NULL,
    CONSTRAINT pk_transaction_log PRIMARY KEY (id_transaction),
    CONSTRAINT ck_transaction_log_state CHECK (state IN ('activa', 'confirmada', 'abortada'))
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_transaction_log_started' AND object_id = OBJECT_ID(N'dist.transaction_log'))
    CREATE INDEX ix_transaction_log_started ON dist.transaction_log (started_at DESC);
GO

IF OBJECT_ID(N'dist.checkpoint_log', N'U') IS NULL
CREATE TABLE dist.checkpoint_log (
    id_checkpoint            BIGINT        IDENTITY(1,1) NOT NULL,
    id_transaction           BIGINT        NOT NULL,   -- el punto de verificación global al que pertenece
    node_name                VARCHAR(20)   NOT NULL,
    db_name                  SYSNAME       NOT NULL,
    status                   VARCHAR(13)   NOT NULL,   -- hecho | sin_respuesta | error
    checkpoint_lsn           NVARCHAR(24)  NULL,
    log_since_checkpoint_mb  DECIMAL(12,2) NULL,
    message                  NVARCHAR(400) NULL,
    taken_at                 DATETIME2(3)  NOT NULL CONSTRAINT df_checkpoint_log_taken DEFAULT SYSUTCDATETIME(),
    CONSTRAINT pk_checkpoint_log PRIMARY KEY (id_checkpoint),
    CONSTRAINT fk_checkpoint_log_transaction FOREIGN KEY (id_transaction) REFERENCES dist.transaction_log (id_transaction),
    CONSTRAINT ck_checkpoint_log_status CHECK (status IN ('hecho', 'sin_respuesta', 'error'))
);
GO

/* ---------------------------------------------------------------------
   4. Traspaso de inventario entre sucursales (transacción distribuida)

   Coordinador: JAM_db. Participantes: los fragmentos de las dos
   sucursales. La misma transacción:
     1. bloquea las dos filas de dbo.inventory en orden de id_store
        (2PL, fase de crecimiento; orden fijo = sin espera circular);
     2. comprueba que el origen tenga existencias (consistencia);
     3. descuenta en el origen y suma en el destino en la central;
     4. copia las dos filas a sus fragmentos (JAM_tijuana, JAM_cdmx o
        JAM_online);
     5. COMMIT: SQL Server prepara y confirma las tres bases juntas (2PC)
        y libera los bloqueos (fase de decrecimiento).
   @pausa_ms solo sirve para la práctica: espera entre el primer y el
   segundo bloqueo para poder provocar choques entre dos sesiones.
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_traspasar_inventario
    @id_variant    INT,
    @desde_store   INT,
    @hacia_store   INT,
    @cantidad      INT,
    @max_intentos  TINYINT = 3,
    @pausa_ms      INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET DEADLOCK_PRIORITY LOW;

    DECLARE @nodo_desde VARCHAR(20), @nodo_hacia VARCHAR(20),
            @db_desde SYSNAME, @db_hacia SYSNAME,
            @id_tx BIGINT, @intento TINYINT = 0, @error INT, @mensaje NVARCHAR(400),
            @primero INT, @segundo INT,
            @id_a INT, @stock_a INT, @borrado_a BIT,
            @id_b INT, @stock_b INT, @borrado_b BIT,
            @id_desde INT, @stock_desde INT, @borrado_desde BIT,
            @id_hacia INT, @borrado_hacia BIT,
            @espera DATETIME, @sql NVARCHAR(MAX);

    DECLARE @plantilla NVARCHAR(MAX) = N'
MERGE {db}.dbo.inventory AS f
USING (SELECT id_inventory, id_store, id_variant, stock, min_stock, source_node, updated_at, deleted
       FROM JAM_db.dbo.inventory
       WHERE id_inventory = @id) AS c
ON f.id_inventory = c.id_inventory
WHEN MATCHED THEN
    UPDATE SET f.stock = c.stock, f.min_stock = c.min_stock, f.updated_at = c.updated_at, f.deleted = c.deleted
WHEN NOT MATCHED THEN
    INSERT (id_inventory, id_store, id_variant, stock, min_stock, source_node, updated_at, deleted)
    VALUES (c.id_inventory, c.id_store, c.id_variant, c.stock, c.min_stock, c.source_node, c.updated_at, c.deleted);';

    /* ---------- Validaciones: antes de abrir la transacción ---------- */
    IF @cantidad IS NULL OR @cantidad <= 0 OR @cantidad > 10000
        THROW 50120, N'La cantidad a traspasar debe estar entre 1 y 10,000 piezas.', 1;

    IF @desde_store IS NULL OR @hacia_store IS NULL OR @desde_store = @hacia_store
        THROW 50121, N'La sucursal de origen y la de destino deben ser distintas.', 1;

    IF @max_intentos IS NULL OR @max_intentos NOT BETWEEN 1 AND 5
        SET @max_intentos = 3;
    IF @pausa_ms IS NULL OR @pausa_ms < 0
        SET @pausa_ms = 0;
    IF @pausa_ms > 10000
        SET @pausa_ms = 10000;

    SELECT @nodo_desde = node_name, @db_desde = db_name FROM dist.node WHERE id_store = @desde_store;
    SELECT @nodo_hacia = node_name, @db_hacia = db_name FROM dist.node WHERE id_store = @hacia_store;

    IF @db_desde IS NULL OR @db_hacia IS NULL
        THROW 50122, N'Las dos sucursales deben ser fragmentos de la base distribuida: 1 Tijuana, 2 CDMX o 4 Tienda en línea.', 1;

    IF NOT EXISTS (SELECT 1 FROM dbo.product_variant WHERE id_variant = @id_variant AND deleted = 0)
        THROW 50123, N'La talla y color (id_variant) no existe.', 1;

    /* Estado "activa". Se registra fuera de la transacción para que la
       bitácora conserve también las transacciones que terminen abortadas. */
    INSERT INTO dist.transaction_log (tx_type, state, participants, detail)
    VALUES ('traspaso_inventario', 'activa', CONCAT('central, ', @nodo_desde, ', ', @nodo_hacia),
            CONCAT(N'Variante ', @id_variant, N': ', @cantidad, N' piezas de ', @nodo_desde, N' a ', @nodo_hacia));
    SET @id_tx = CAST(SCOPE_IDENTITY() AS BIGINT);

    /* Atomicidad distribuida: si un participante no está en línea la
       transacción no empieza. Nunca queda confirmada en unas bases y en
       otras no. */
    IF dist.fn_nodo_en_linea(@db_desde) = 0 OR dist.fn_nodo_en_linea(@db_hacia) = 0
    BEGIN
        SET @mensaje = N'Una de las sucursales no está en línea: el traspaso se abortó sin cambiar nada (todo o nada).';
        UPDATE dist.transaction_log
        SET state = 'abortada', error_number = 50124, error_message = @mensaje, ended_at = SYSUTCDATETIME()
        WHERE id_transaction = @id_tx;
        THROW 50124, @mensaje, 1;
    END;

    SET @primero = CASE WHEN @desde_store < @hacia_store THEN @desde_store ELSE @hacia_store END;
    SET @segundo = CASE WHEN @desde_store < @hacia_store THEN @hacia_store ELSE @desde_store END;

    WHILE @intento < @max_intentos
    BEGIN
        SET @intento += 1;
        SELECT @id_a = NULL, @stock_a = NULL, @borrado_a = NULL,
               @id_b = NULL, @stock_b = NULL, @borrado_b = NULL;

        BEGIN TRY
            BEGIN TRANSACTION;

            /* Fase de crecimiento (2PL): bloqueo de actualización sobre las
               dos filas, siempre en el mismo orden. HOLDLOCK también bloquea
               el hueco si la fila no existe, así nadie la crea a la vez. */
            SELECT @id_a = id_inventory, @stock_a = stock, @borrado_a = deleted
            FROM dbo.inventory WITH (UPDLOCK, HOLDLOCK, ROWLOCK)
            WHERE id_store = @primero AND id_variant = @id_variant;

            IF @pausa_ms > 0
            BEGIN
                SET @espera = DATEADD(MILLISECOND, @pausa_ms, CAST(0 AS DATETIME));
                WAITFOR DELAY @espera;
            END;

            SELECT @id_b = id_inventory, @stock_b = stock, @borrado_b = deleted
            FROM dbo.inventory WITH (UPDLOCK, HOLDLOCK, ROWLOCK)
            WHERE id_store = @segundo AND id_variant = @id_variant;

            IF @primero = @desde_store
                SELECT @id_desde = @id_a, @stock_desde = @stock_a, @borrado_desde = @borrado_a,
                       @id_hacia = @id_b, @borrado_hacia = @borrado_b;
            ELSE
                SELECT @id_desde = @id_b, @stock_desde = @stock_b, @borrado_desde = @borrado_b,
                       @id_hacia = @id_a, @borrado_hacia = @borrado_a;

            /* Consistencia: nunca existencias negativas (además del CHECK de la tabla) */
            IF @id_desde IS NULL OR @borrado_desde = 1 OR @stock_desde < @cantidad
                THROW 50125, N'La sucursal de origen no tiene existencias suficientes de esa talla y color.', 1;

            IF @id_hacia IS NULL
            BEGIN
                INSERT INTO dbo.inventory (id_store, id_variant, stock, min_stock, source_node)
                VALUES (@hacia_store, @id_variant, 0, 0, 'transfer_node');
                SET @id_hacia = CAST(SCOPE_IDENTITY() AS INT);
            END;

            /* Coordinador: nodo central */
            UPDATE dbo.inventory SET stock = stock - @cantidad WHERE id_inventory = @id_desde;
            UPDATE dbo.inventory SET stock = stock + @cantidad, deleted = 0 WHERE id_inventory = @id_hacia;

            /* Participantes: fragmentos de las dos sucursales */
            SET @sql = REPLACE(@plantilla, N'{db}', QUOTENAME(@db_desde));
            EXEC sys.sp_executesql @sql, N'@id INT', @id = @id_desde;

            SET @sql = REPLACE(@plantilla, N'{db}', QUOTENAME(@db_hacia));
            EXEC sys.sp_executesql @sql, N'@id INT', @id = @id_hacia;

            /* Decisión: confirmar en las tres bases (2PC interno) */
            COMMIT TRANSACTION;

            UPDATE dist.transaction_log
            SET state = 'confirmada', attempts = @intento, ended_at = SYSUTCDATETIME()
            WHERE id_transaction = @id_tx;

            BREAK;
        END TRY
        BEGIN CATCH
            SET @error = ERROR_NUMBER();
            SET @mensaje = LEFT(ERROR_MESSAGE(), 400);
            IF @@TRANCOUNT > 0
                ROLLBACK TRANSACTION;

            /* Víctima de un interbloqueo: ya se deshizo todo; se espera
               50, 100, 200 ms (espera creciente) y se vuelve a intentar. */
            IF @error = 1205 AND @intento < @max_intentos
            BEGIN
                SET @espera = DATEADD(MILLISECOND, 50 * POWER(2, @intento - 1), CAST(0 AS DATETIME));
                WAITFOR DELAY @espera;
                CONTINUE;
            END;

            UPDATE dist.transaction_log
            SET state = 'abortada', attempts = @intento, error_number = @error,
                error_message = @mensaje, ended_at = SYSUTCDATETIME()
            WHERE id_transaction = @id_tx;

            THROW;
        END CATCH;
    END;

    SELECT l.id_transaction,
           l.state        AS estado,
           l.attempts     AS intentos,
           l.participants AS participantes,
           (SELECT stock FROM dbo.inventory WHERE id_inventory = @id_desde) AS stock_origen,
           (SELECT stock FROM dbo.inventory WHERE id_inventory = @id_hacia) AS stock_destino
    FROM dist.transaction_log AS l
    WHERE l.id_transaction = @id_tx;
END;
GO

/* ---------------------------------------------------------------------
   5. Punto de verificación global (4.3.4)

   El nodo central es el coordinador:
     1. registra la transacción "punto_verificacion" como activa;
     2. pide un CHECKPOINT a cada nodo (CHECKPOINT-REQUEST);
     3. guarda el LSN del checkpoint de cada nodo (CHECKPOINT-DONE);
     4. registra el punto global como confirmado solo si todos los nodos
        respondieron; si alguno no, el corte queda incompleto (abortada).
   El espejo en Azure SQL no entra: Azure administra sus checkpoints.
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_punto_verificacion_global
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @id_tx BIGINT, @nodo VARCHAR(20), @db SYSNAME, @sql NVARCHAR(MAX),
            @lsn NVARCHAR(24), @mb DECIMAL(12,2), @fallas INT = 0, @total INT = 0;

    INSERT INTO dist.transaction_log (tx_type, state, participants, detail)
    SELECT 'punto_verificacion', 'activa',
           STRING_AGG(node_name, ', ') WITHIN GROUP (ORDER BY node_name),
           N'CHECKPOINT-REQUEST enviado a todos los nodos'
    FROM dist.node
    WHERE node_role <> 'nube';
    SET @id_tx = CAST(SCOPE_IDENTITY() AS BIGINT);

    DECLARE nodos CURSOR LOCAL FAST_FORWARD FOR
        SELECT node_name, db_name
        FROM dist.node
        WHERE node_role <> 'nube'
        ORDER BY CASE WHEN node_role = 'central' THEN 0 ELSE 1 END, node_name;

    OPEN nodos;
    FETCH NEXT FROM nodos INTO @nodo, @db;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @total += 1;
        SELECT @lsn = NULL, @mb = NULL;

        IF dist.fn_nodo_en_linea(@db) = 0
        BEGIN
            SET @fallas += 1;
            INSERT INTO dist.checkpoint_log (id_transaction, node_name, db_name, status, message)
            VALUES (@id_tx, @nodo, @db, 'sin_respuesta', N'La base no está en línea.');
        END
        ELSE
        BEGIN
            BEGIN TRY
                SET @sql = N'USE ' + QUOTENAME(@db) + N'; CHECKPOINT;';
                EXEC sys.sp_executesql @sql;

                /* LSN del checkpoint; sin VIEW SERVER STATE queda vacío pero el checkpoint sí se hizo */
                BEGIN TRY
                    SELECT @lsn = checkpoint_lsn,
                           @mb  = CAST(log_since_last_checkpoint_mb AS DECIMAL(12,2))
                    FROM sys.dm_db_log_stats(DB_ID(@db));
                END TRY
                BEGIN CATCH
                    SELECT @lsn = NULL, @mb = NULL;
                END CATCH;

                INSERT INTO dist.checkpoint_log (id_transaction, node_name, db_name, status, checkpoint_lsn, log_since_checkpoint_mb)
                VALUES (@id_tx, @nodo, @db, 'hecho', @lsn, @mb);
            END TRY
            BEGIN CATCH
                SET @fallas += 1;
                INSERT INTO dist.checkpoint_log (id_transaction, node_name, db_name, status, message)
                VALUES (@id_tx, @nodo, @db, 'error', LEFT(ERROR_MESSAGE(), 400));
            END CATCH;
        END;

        FETCH NEXT FROM nodos INTO @nodo, @db;
    END;

    CLOSE nodos;
    DEALLOCATE nodos;

    UPDATE dist.transaction_log
    SET state    = CASE WHEN @fallas = 0 THEN 'confirmada' ELSE 'abortada' END,
        attempts = 1,
        detail   = CASE WHEN @fallas = 0
                        THEN CONCAT(N'Punto de verificación global registrado: ', @total, N' nodos respondieron CHECKPOINT-DONE.')
                        ELSE CONCAT(N'Corte incompleto: ', @fallas, N' de ', @total, N' nodos no respondieron; no hay punto de verificación global.')
                   END,
        ended_at = SYSUTCDATETIME()
    WHERE id_transaction = @id_tx;

    SELECT l.id_transaction,
           l.state                   AS estado_global,
           c.node_name               AS nodo,
           c.db_name                 AS base,
           c.status                  AS estado,
           c.checkpoint_lsn,
           c.log_since_checkpoint_mb AS bitacora_desde_checkpoint_mb,
           c.message                 AS mensaje
    FROM dist.transaction_log AS l
    JOIN dist.checkpoint_log AS c ON c.id_transaction = l.id_transaction
    WHERE l.id_transaction = @id_tx
    ORDER BY c.id_checkpoint;
END;
GO

/* ---------------------------------------------------------------------
   6. Monitoreo de la concurrencia
   --------------------------------------------------------------------- */

/* Grafo de espera: una fila por arco "sesión que espera -> sesión que
   bloquea". Un ciclo en este grafo es un interbloqueo. */
CREATE OR ALTER VIEW dist.vw_grafo_espera
AS
SELECT w.session_id             AS sesion_que_espera,
       w.blocking_session_id    AS sesion_que_bloquea,
       w.wait_type              AS tipo_espera,
       w.wait_duration_ms       AS espera_ms,
       DB_NAME(r.database_id)   AS base,
       r.command                AS instruccion,
       w.resource_description   AS recurso
FROM sys.dm_os_waiting_tasks AS w
JOIN sys.dm_exec_requests AS r ON r.session_id = w.session_id
WHERE w.blocking_session_id IS NOT NULL
  AND w.blocking_session_id <> w.session_id;
GO

/* Transacciones abiertas con su estado (los de la unidad) y su nivel de aislamiento */
CREATE OR ALTER VIEW dist.vw_transacciones_activas
AS
SELECT st.session_id                     AS sesion,
       at.transaction_id                 AS id_transaccion,
       at.name                           AS nombre,
       at.transaction_begin_time         AS inicio,
       CASE at.transaction_state
            WHEN 0 THEN 'iniciando'
            WHEN 1 THEN 'iniciada, sin operaciones'
            WHEN 2 THEN 'activa'
            WHEN 3 THEN 'terminada (solo lectura)'
            WHEN 4 THEN 'confirmando (2PC distribuido)'
            WHEN 5 THEN 'preparada (espera la decisión)'
            WHEN 6 THEN 'confirmada'
            WHEN 7 THEN 'deshaciéndose'
            WHEN 8 THEN 'abortada'
       END                               AS estado,
       CASE s.transaction_isolation_level
            WHEN 1 THEN 'READ UNCOMMITTED'
            WHEN 2 THEN 'READ COMMITTED'
            WHEN 3 THEN 'REPEATABLE READ'
            WHEN 4 THEN 'SERIALIZABLE'
            WHEN 5 THEN 'SNAPSHOT'
            ELSE 'sin especificar'
       END                               AS aislamiento,
       s.program_name                    AS programa,
       DB_NAME(s.database_id)            AS base
FROM sys.dm_tran_active_transactions AS at
JOIN sys.dm_tran_session_transactions AS st ON st.transaction_id = at.transaction_id
JOIN sys.dm_exec_sessions AS s ON s.session_id = st.session_id;
GO

/* ---------------------------------------------------------------------
   7. Comprobación
   --------------------------------------------------------------------- */
SELECT name                          AS base,
       is_read_committed_snapshot_on AS lecturas_con_versiones,
       snapshot_isolation_state_desc AS aislamiento_snapshot
FROM sys.databases
WHERE name IN (N'JAM_db', N'JAM_tijuana', N'JAM_cdmx', N'JAM_online')
ORDER BY name;
GO

SELECT OBJECT_SCHEMA_NAME(object_id) + N'.' + name AS objeto, type_desc AS tipo
FROM sys.objects
WHERE SCHEMA_NAME(schema_id) = N'dist'
  AND name IN (N'transaction_log', N'checkpoint_log', N'sp_traspasar_inventario',
               N'sp_punto_verificacion_global', N'vw_grafo_espera', N'vw_transacciones_activas')
ORDER BY name;
GO
