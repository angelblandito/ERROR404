/* =====================================================================
   JAM - Base de datos distribuida
   05. Validación de la fragmentación
   ---------------------------------------------------------------------
   Comprueba las tres reglas de una fragmentación correcta:
     1. Completitud:     todo renglón de la relación está en algún fragmento.
     2. Reconstrucción:  la unión (horizontal) o el JOIN (vertical) de los
                         fragmentos devuelve exactamente la relación original.
     3. Disyunción:      ningún renglón aparece en dos fragmentos horizontales.
   En cada consulta el resultado esperado es 0 diferencias / 'OK'.
   ===================================================================== */

USE JAM_db;
GO

EXEC dist.sp_sincronizar_nodos;
GO

/* ---------- Horizontal: inventory ---------- */
SELECT
    'inventory' AS relacion,
    (SELECT COUNT(*) FROM dbo.inventory WHERE id_store IN (SELECT id_store FROM dist.node WHERE id_store IS NOT NULL)) AS renglones_original,
    (SELECT COUNT(*) FROM dist.vw_inventory_global) AS renglones_fragmentos,
    (SELECT COUNT(*) FROM (
        SELECT id_inventory, id_store, id_variant, stock, min_stock
        FROM dbo.inventory
        WHERE id_store IN (SELECT id_store FROM dist.node WHERE id_store IS NOT NULL)
        EXCEPT
        SELECT id_inventory, id_store, id_variant, stock, min_stock
        FROM dist.vw_inventory_global
    ) AS faltantes) AS diferencias,
    (SELECT COUNT(*) FROM (
        SELECT id_inventory FROM dist.vw_inventory_global GROUP BY id_inventory HAVING COUNT(*) > 1
    ) AS repetidos) AS repetidos_entre_fragmentos;
GO

/* ---------- Horizontal: customer_order ---------- */
SELECT
    'customer_order' AS relacion,
    (SELECT COUNT(*) FROM dbo.customer_order WHERE id_store IN (SELECT id_store FROM dist.node WHERE id_store IS NOT NULL)) AS renglones_original,
    (SELECT COUNT(*) FROM dist.vw_customer_order_global) AS renglones_fragmentos,
    (SELECT COUNT(*) FROM (
        SELECT id_order, id_store, id_customer, status, channel, total
        FROM dbo.customer_order
        WHERE id_store IN (SELECT id_store FROM dist.node WHERE id_store IS NOT NULL)
        EXCEPT
        SELECT id_order, id_store, id_customer, status, channel, total
        FROM dist.vw_customer_order_global
    ) AS faltantes) AS diferencias,
    (SELECT COUNT(*) FROM (
        SELECT id_order FROM dist.vw_customer_order_global GROUP BY id_order HAVING COUNT(*) > 1
    ) AS repetidos) AS repetidos_entre_fragmentos;
GO

/* ---------- Horizontal: el predicado se cumple en cada nodo ---------- */
SELECT g.node_name, n.id_store AS predicado_id_store,
       SUM(CASE WHEN g.id_store = n.id_store THEN 1 ELSE 0 END) AS cumplen,
       SUM(CASE WHEN g.id_store <> n.id_store THEN 1 ELSE 0 END) AS no_cumplen
FROM dist.vw_inventory_global AS g
JOIN dist.node AS n ON n.node_name = g.node_name
GROUP BY g.node_name, n.id_store;
GO

/* ---------- Vertical: customer ---------- */
SELECT
    'customer (vertical)' AS relacion,
    (SELECT COUNT(*) FROM dbo.customer) AS renglones_original,
    (SELECT COUNT(*) FROM dist.vw_customer_reconstruido) AS renglones_reconstruidos,
    (SELECT COUNT(*) FROM (
        SELECT id_customer, id_loyalty_level, id_store, id_address, first_name, last_name_paternal,
               last_name_maternal, birth_date, sex, phone, email, accepts_marketing
        FROM dbo.customer
        EXCEPT
        SELECT id_customer, id_loyalty_level, id_store, id_address, first_name, last_name_paternal,
               last_name_maternal, birth_date, sex, phone, email, accepts_marketing
        FROM dist.vw_customer_reconstruido
    ) AS faltantes) AS diferencias;
GO

/* ---------- Vertical: cada fragmento solo tiene sus columnas + la llave ---------- */
SELECT 'JAM_online.dbo.customer_profile' AS fragmento, name AS columna
FROM JAM_online.sys.columns WHERE object_id = OBJECT_ID(N'JAM_online.dbo.customer_profile')
UNION ALL
SELECT 'JAM_db.dist.customer_private', name
FROM sys.columns WHERE object_id = OBJECT_ID(N'dist.customer_private');
GO
