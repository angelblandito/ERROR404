<?php
/*
 * Copia de JAM_db (nodo central) hacia JAM_cloud (Azure SQL).
 *
 * SQL Server Express no puede publicar replicación, así que la API copia
 * las tablas al espejo. Azure replica después JAM_cloud al servidor
 * secundario del grupo de conmutación (geo-replicación).
 *
 *   POST /mirror/sync         copia los cambios desde la última vez
 *   POST /mirror/sync?full=1  vuelve a copiar todas las tablas
 *
 * Copia por cambios: se toman los renglones con updated_at mayor o igual
 * a la marca guardada en dbo.mirror_sync. Si el número de renglones no
 * coincide (hubo borrados), la tabla se copia completa.
 *
 * Layer: services. Logica de negocio y SQL; sin HTTP. Las rutas viven en routers y los
 * controllers de controllers llaman a estas funciones con el actor de la sesion.
 */

const MIRROR_TABLES = [
    'role', 'store_type', 'category', 'size', 'color', 'loyalty_level', 'account_tier', 'absence_type',
    'state', 'season_theme', 'store', 'store_config', 'product', 'product_variant', 'promotion',
    'employee', 'inventory', 'customer', 'customer_order', 'order_item', 'payment',
];

/* Límite de parámetros por sentencia en SQL Server: 2100. */
const MIRROR_MAX_PARAMS = 2000;

function mirror_enabled(): bool
{
    return isset(jam_nodes_config()['cloud']);
}

function mirror_quote(string $name): string
{
    return '[' . str_replace(']', ']]', $name) . ']';
}

/* Columnas copiables (sin rowversion ni calculadas), en orden. */
function mirror_columns(PDO $pdo, string $table): array
{
    $rows = dist_query($pdo, '
        SELECT name FROM sys.columns
        WHERE object_id = OBJECT_ID(:t) AND system_type_id <> 189 AND is_computed = 0
        ORDER BY column_id', [':t' => 'dbo.' . $table]);
    return array_column($rows, 'name');
}

function mirror_primary_key(PDO $pdo, string $table): array
{
    $rows = dist_query($pdo, '
        SELECT c.name
        FROM sys.indexes AS i
        JOIN sys.index_columns AS ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
        JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
        WHERE i.object_id = OBJECT_ID(:t) AND i.is_primary_key = 1
        ORDER BY ic.key_ordinal', [':t' => 'dbo.' . $table]);
    return array_column($rows, 'name');
}

/* Conteo y mayor updated_at de todas las tablas en una sola consulta. */
function mirror_table_stats(PDO $pdo, array $tables, array $maxUpdatedFor = []): array
{
    $parts = [];
    foreach ($tables as $table) {
        $max = in_array($table, $maxUpdatedFor, true)
            ? 'CONVERT(VARCHAR(23), MAX(updated_at), 121)'
            : 'CAST(NULL AS VARCHAR(23))';
        $parts[] = "SELECT '$table' AS t, COUNT(*) AS n, $max AS max_updated FROM dbo." . mirror_quote($table);
    }
    $stats = [];
    foreach (dist_query($pdo, implode(' UNION ALL ', $parts)) as $r) {
        $stats[$r['t']] = ['rows' => (int)$r['n'], 'max_updated' => $r['max_updated']];
    }
    return $stats;
}

function mirror_insert(PDO $cloud, string $table, array $columns, array $rows): void
{
    if (!$rows) {
        return;
    }
    $quoted = implode(', ', array_map('mirror_quote', $columns));
    $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $perBatch = max(1, intdiv(MIRROR_MAX_PARAMS, count($columns)));

    foreach (array_chunk($rows, $perBatch) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            foreach ($columns as $c) {
                $values[] = $row[$c];
            }
        }
        $sql = 'INSERT INTO dbo.' . mirror_quote($table) . " ($quoted) VALUES "
            . implode(', ', array_fill(0, count($chunk), $placeholders));
        $cloud->prepare($sql)->execute($values);
    }
}

function mirror_delete_keys(PDO $cloud, string $table, array $key, array $rows): void
{
    if (!$rows) {
        return;
    }
    $condition = '(' . implode(' AND ', array_map(function ($c) {
        return mirror_quote($c) . ' = ?';
    }, $key)) . ')';
    $perBatch = max(1, intdiv(MIRROR_MAX_PARAMS, count($key)));

    foreach (array_chunk($rows, $perBatch) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            foreach ($key as $c) {
                $values[] = $row[$c];
            }
        }
        $sql = 'DELETE FROM dbo.' . mirror_quote($table) . ' WHERE '
            . implode(' OR ', array_fill(0, count($chunk), $condition));
        $cloud->prepare($sql)->execute($values);
    }
}

function mirror_save_mark(PDO $cloud, string $table, int $rows, ?string $watermark): void
{
    $cloud->prepare('
        MERGE dbo.mirror_sync AS t
        USING (SELECT :t AS table_name) AS s ON t.table_name = s.table_name
        WHEN MATCHED THEN UPDATE SET rows_copied = :n, watermark = :w, synced_at = SYSUTCDATETIME()
        WHEN NOT MATCHED THEN INSERT (table_name, rows_copied, watermark, synced_at)
                              VALUES (:t2, :n2, :w2, SYSUTCDATETIME());')
        ->execute([':t' => $table, ':n' => $rows, ':w' => $watermark, ':t2' => $table, ':n2' => $rows, ':w2' => $watermark]);
}

/*
 * Copia el nodo central al espejo. Devuelve el detalle por tabla.
 * Con $full = true todas las tablas se borran y se vuelven a copiar.
 */
function mirror_copy(bool $full = false): array
{
    if (!mirror_enabled()) {
        throw new RuntimeException('El espejo en Azure no está configurado (faltan JAM_CLOUD_USER y JAM_CLOUD_PASSWORD).');
    }

    $central = jam_node('central');
    $cloud = jam_node('cloud');
    $cloudInfo = jam_node_info('cloud');

    if (($cloudInfo['endpoint'] ?? null) === 'espejo') {
        throw new RuntimeException('El servidor principal de Azure no responde; el espejo secundario es de solo lectura.');
    }

    $columns = [];
    $keys = [];
    $hasUpdatedAt = [];
    foreach (MIRROR_TABLES as $table) {
        $cloudColumns = mirror_columns($cloud, $table);
        if (!$cloudColumns) {
            throw new RuntimeException("La tabla $table no existe en JAM_cloud. Ejecute API_JAM/distribuida/04_espejo_azure.sql en Azure.");
        }
        $columns[$table] = array_values(array_intersect($cloudColumns, mirror_columns($central, $table)));
        $keys[$table] = mirror_primary_key($cloud, $table);
        if (in_array('updated_at', $columns[$table], true)) {
            $hasUpdatedAt[] = $table;
        }
    }

    $centralStats = mirror_table_stats($central, MIRROR_TABLES, $hasUpdatedAt);
    $cloudStats = mirror_table_stats($cloud, MIRROR_TABLES);

    $marks = [];
    foreach (dist_query($cloud, 'SELECT table_name, rows_copied, CONVERT(VARCHAR(23), watermark, 121) AS watermark FROM dbo.mirror_sync') as $m) {
        $marks[$m['table_name']] = $m;
    }

    $result = [];
    foreach (MIRROR_TABLES as $table) {
        $cols = $columns[$table];
        $quoted = implode(', ', array_map('mirror_quote', $cols));
        $source = $centralStats[$table];
        $mark = $marks[$table] ?? null;
        $watermark = $mark['watermark'] ?? null;
        $incremental = !$full && $mark !== null && $watermark !== null && in_array($table, $hasUpdatedAt, true);

        $item = ['table' => $table, 'mode' => 'sin_cambios', 'rows' => 0, 'total' => $source['rows']];

        $changed = $mark === null
            || $source['max_updated'] !== $watermark
            || $source['rows'] !== $cloudStats[$table]['rows'];

        if (!$full && !$changed) {
            $result[] = $item;
            continue;
        }

        $cloud->beginTransaction();
        try {
            $rows = [];
            if ($incremental) {
                $rows = dist_query($central, "SELECT $quoted FROM dbo." . mirror_quote($table) . ' WHERE updated_at >= :w', [':w' => $watermark]);
                mirror_delete_keys($cloud, $table, $keys[$table], $rows);
                mirror_insert($cloud, $table, $cols, $rows);

                $count = (int)dist_query($cloud, 'SELECT COUNT(*) AS n FROM dbo.' . mirror_quote($table))[0]['n'];
                $item['mode'] = 'cambios';
                if ($count !== $source['rows']) {
                    $incremental = false;
                }
            }
            if (!$incremental) {
                $rows = dist_query($central, "SELECT $quoted FROM dbo." . mirror_quote($table));
                $cloud->exec('DELETE FROM dbo.' . mirror_quote($table));
                mirror_insert($cloud, $table, $cols, $rows);
                $item['mode'] = 'completa';
            }
            mirror_save_mark($cloud, $table, $source['rows'], $source['max_updated']);
            $cloud->commit();
        } catch (Throwable $e) {
            if ($cloud->inTransaction()) {
                $cloud->rollBack();
            }
            throw new RuntimeException("Copia de $table: " . dist_error_text($e), 0, $e);
        }

        $item['rows'] = count($rows);
        $result[] = $item;
    }

    return [
        'copied_at' => gmdate('Y-m-d H:i:s'),
        'server' => $cloudInfo['server'] ?? null,
        'tables' => $result,
        'changed' => count(array_filter($result, function ($t) {
            return $t['mode'] !== 'sin_cambios';
        })),
    ];
}

/* Última copia al espejo: la fecha más reciente de dbo.mirror_sync. */
function mirror_last_copy(PDO $cloud): ?string
{
    try {
        $row = dist_query($cloud, 'SELECT CONVERT(VARCHAR(19), MAX(synced_at), 120) AS last_copy FROM dbo.mirror_sync');
        return $row[0]['last_copy'] ?? null;
    } catch (Throwable $e) {
        return null;
    }
}

