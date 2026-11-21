/* =====================================================================
   JAM - Base de datos distribuida
   07. Transparencia ante fallos de un nodo
   ---------------------------------------------------------------------
   Requisitos: 01, 02 y 06 ejecutados (y 04 si se usa el espejo en Azure).

   Qué pasa cuando una sucursal se cae:
     1. La tienda no se detiene: los pedidos, el inventario y los clientes
        se escriben en el nodo central JAM_db.
     2. Las consultas del fragmento caído se responden desde otra copia:
          a) el espejo en Azure SQL (JAM_cloud, grupo de conmutación jam-fog)
          b) el nodo central JAM_db, filtrando por el predicado id_store
     3. La sincronización refresca los nodos que sí responden y marca el
        nodo caído como pendiente en dist.node_sync.
     4. Al volver a línea, la siguiente sincronización lo pone al día
        (la API la ejecuta sola en cuanto detecta que el nodo respondió).
   ===================================================================== */

USE JAM_db;
GO

/* ---------------------------------------------------------------------
   Estado de cada nodo fragmento
   --------------------------------------------------------------------- */
CREATE OR ALTER VIEW dist.vw_estado_nodos AS
    SELECT
        n.node_name,
        n.db_name,
        n.id_store,
        CAST(ISNULL(DATABASEPROPERTYEX(n.db_name, 'Status'), 'NO EXISTE') AS VARCHAR(20)) AS estado,
        dist.fn_nodo_en_linea(n.db_name) AS en_linea,
        s.pending      AS pendiente,
        s.last_success AS ultima_sincronizacion,
        s.last_error   AS ultimo_error
    FROM dist.node AS n
    LEFT JOIN dist.node_sync AS s ON s.node_name = n.node_name
    WHERE n.id_store IS NOT NULL;
GO

/* ---------------------------------------------------------------------
   Consulta global tolerante a fallos.
   Reconstruye la relación con UNION ALL de los fragmentos igual que las
   vistas dist.vw_*_global, pero cada nodo fuera de línea se sustituye por
   su copia en el nodo central (mismo predicado id_store = N).
   La columna "origen" indica de dónde salió cada renglón.
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dist.sp_consulta_global
    @tabla SYSNAME
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @columnas NVARCHAR(400) = CASE @tabla
        WHEN N'inventory'      THEN N'id_inventory, id_store, id_variant, stock, min_stock, updated_at'
        WHEN N'customer_order' THEN N'id_order, id_store, id_customer, order_date, status, channel, total'
        WHEN N'employee'       THEN N'id_employee, id_store, id_role, first_name, last_name_paternal, email, hire_date'
    END;

    IF @columnas IS NULL
        THROW 50101, N'Tabla no fragmentada. Use inventory, customer_order o employee.', 1;

    DECLARE @sql NVARCHAR(MAX);

    SELECT @sql = STRING_AGG(CAST(
        CASE WHEN dist.fn_nodo_en_linea(db_name) = 1
             THEN N'SELECT ''' + node_name + N''' AS nodo, ''fragmento ' + db_name + N''' AS origen, '
                  + @columnas + N' FROM ' + QUOTENAME(db_name) + N'.dbo.' + QUOTENAME(@tabla)
             ELSE N'SELECT ''' + node_name + N''' AS nodo, ''respaldo JAM_db'' AS origen, '
                  + @columnas + N' FROM JAM_db.dbo.' + QUOTENAME(@tabla)
                  + N' WHERE id_store = ' + CAST(id_store AS NVARCHAR(10))
        END AS NVARCHAR(MAX)), N' UNION ALL ') WITHIN GROUP (ORDER BY id_store)
    FROM dist.node
    WHERE id_store IS NOT NULL;

    EXEC sys.sp_executesql @sql;
END;
GO

/* Resumen por nodo: cuántos renglones aporta cada uno y de dónde salen. */
CREATE OR ALTER PROCEDURE dist.sp_resumen_global
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @r TABLE (nodo VARCHAR(20), origen VARCHAR(40), id_inventory INT, id_store INT, id_variant INT,
                      stock INT, min_stock INT, updated_at DATETIME2(3));

    INSERT INTO @r EXEC dist.sp_consulta_global @tabla = N'inventory';

    SELECT nodo, origen, COUNT(*) AS renglones, SUM(stock) AS piezas
    FROM @r
    GROUP BY nodo, origen
    ORDER BY nodo;
END;
GO

/* =====================================================================
   Demostración en clase (ejecutar una por una, no todo el archivo).

   -- 1) Estado inicial: los tres nodos ONLINE y sin pendientes
   SELECT * FROM dist.vw_estado_nodos;
   EXEC dist.sp_resumen_global;

   -- 2) Tumbar la sucursal Tijuana
   ALTER DATABASE JAM_tijuana SET OFFLINE WITH ROLLBACK IMMEDIATE;

   -- 3) La vista particionada normal ya no funciona...
   SELECT COUNT(*) FROM dist.vw_inventory_global;      -- error 942

   -- 4) ...pero la consulta tolerante sigue respondiendo con los tres nodos:
   --    Tijuana sale de "respaldo JAM_db"
   EXEC dist.sp_resumen_global;
   EXEC dist.sp_consulta_global @tabla = N'customer_order';

   -- 5) Seguir trabajando: hacer una compra en la tienda con recoger en
   --    Tijuana, o cambiar existencias desde el panel. Después:
   EXEC dist.sp_sincronizar_nodos;                      -- pending_nodes = 1
   SELECT * FROM dist.vw_estado_nodos;                  -- tijuana pendiente

   -- 6) Levantar la sucursal y ponerla al día
   ALTER DATABASE JAM_tijuana SET ONLINE;
   EXEC dist.sp_sincronizar_nodos;                      -- pending_nodes = 0
   SELECT * FROM dist.vw_estado_nodos;
   EXEC dist.sp_resumen_global;                         -- Tijuana vuelve a "fragmento"
   ===================================================================== */
