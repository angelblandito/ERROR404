<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Copy of JAM_db (central node) to JAM_cloud (Azure SQL).
 *
 * SQL Server Express cannot publish replication, so the API copies the
 * tables to the mirror. Azure then replicates JAM_cloud to the secondary
 * server of the failover group (geo-replication).
 *
 *   POST /mirror/sync         copy the changes since the last time
 *   POST /mirror/sync?full=1  copy every table again
 *
 * Incremental copy: the rows with updated_at greater than or equal to the
 * mark saved in dbo.mirror_sync. When the row count does not match (rows
 * were deleted), the whole table is copied.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class MirrorService
{
    public const TABLES = [
        'role', 'store_type', 'category', 'size', 'color', 'loyalty_level', 'account_tier', 'absence_type',
        'state', 'season_theme', 'store', 'store_config', 'product', 'product_variant', 'promotion',
        'employee', 'inventory', 'customer', 'customer_order', 'order_item', 'payment',
    ];

    /* SQL Server limit of parameters per statement: 2100. */
    public const MAX_PARAMS = 2000;

    public static function enabled(): bool
    {
        return isset(Database::config()['cloud']);
    }

    public static function quote(string $name): string
    {
        return '[' . str_replace(']', ']]', $name) . ']';
    }

    /* Copyable columns (no rowversion, no computed ones), in order. */
    public static function columns(PDO $pdo, string $table): array
    {
        $rows = DistributedService::query($pdo, '
            SELECT name FROM sys.columns
            WHERE object_id = OBJECT_ID(:t) AND system_type_id <> 189 AND is_computed = 0
            ORDER BY column_id', [':t' => 'dbo.' . $table]);
        return array_column($rows, 'name');
    }

    public static function primaryKey(PDO $pdo, string $table): array
    {
        $rows = DistributedService::query($pdo, '
            SELECT c.name
            FROM sys.indexes AS i
            JOIN sys.index_columns AS ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
            JOIN sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
            WHERE i.object_id = OBJECT_ID(:t) AND i.is_primary_key = 1
            ORDER BY ic.key_ordinal', [':t' => 'dbo.' . $table]);
        return array_column($rows, 'name');
    }

    /* Count and latest updated_at of every table in one query. */
    public static function tableStats(PDO $pdo, array $tables, array $maxUpdatedFor = []): array
    {
        $parts = [];
        foreach ($tables as $table) {
            $max = in_array($table, $maxUpdatedFor, true)
                ? 'CONVERT(VARCHAR(23), MAX(updated_at), 121)'
                : 'CAST(NULL AS VARCHAR(23))';
            $parts[] = "SELECT '$table' AS t, COUNT(*) AS n, $max AS max_updated FROM dbo." . self::quote($table);
        }
        $stats = [];
        foreach (DistributedService::query($pdo, implode(' UNION ALL ', $parts)) as $r) {
            $stats[$r['t']] = ['rows' => (int)$r['n'], 'max_updated' => $r['max_updated']];
        }
        return $stats;
    }

    public static function insert(PDO $cloud, string $table, array $columns, array $rows): void
    {
        if (!$rows) {
            return;
        }
        $quoted = implode(', ', array_map([self::class, 'quote'], $columns));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $perBatch = max(1, intdiv(self::MAX_PARAMS, count($columns)));

        foreach (array_chunk($rows, $perBatch) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                foreach ($columns as $c) {
                    $values[] = $row[$c];
                }
            }
            $sql = 'INSERT INTO dbo.' . self::quote($table) . " ($quoted) VALUES "
                . implode(', ', array_fill(0, count($chunk), $placeholders));
            $cloud->prepare($sql)->execute($values);
        }
    }

    public static function deleteKeys(PDO $cloud, string $table, array $key, array $rows): void
    {
        if (!$rows) {
            return;
        }
        $condition = '(' . implode(' AND ', array_map(function ($c) {
            return self::quote($c) . ' = ?';
        }, $key)) . ')';
        $perBatch = max(1, intdiv(self::MAX_PARAMS, count($key)));

        foreach (array_chunk($rows, $perBatch) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                foreach ($key as $c) {
                    $values[] = $row[$c];
                }
            }
            $sql = 'DELETE FROM dbo.' . self::quote($table) . ' WHERE '
                . implode(' OR ', array_fill(0, count($chunk), $condition));
            $cloud->prepare($sql)->execute($values);
        }
    }

    public static function saveMark(PDO $cloud, string $table, int $rows, ?string $watermark): void
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
     * Copy the central node to the mirror. Returns the detail per table.
     * With $full = true every table is emptied and copied again.
     */
    public static function copy(bool $full = false): array
    {
        if (!self::enabled()) {
            throw new RuntimeException('El espejo en Azure no está configurado (faltan JAM_CLOUD_USER y JAM_CLOUD_PASSWORD).');
        }

        $central = Database::node('central');
        $cloud = Database::node('cloud');
        $cloudInfo = Database::info('cloud');

        if (($cloudInfo['endpoint'] ?? null) === 'espejo') {
            throw new RuntimeException('El servidor principal de Azure no responde; el espejo secundario es de solo lectura.');
        }

        $columns = [];
        $keys = [];
        $hasUpdatedAt = [];
        foreach (self::TABLES as $table) {
            $cloudColumns = self::columns($cloud, $table);
            if (!$cloudColumns) {
                throw new RuntimeException("La tabla $table no existe en JAM_cloud. Ejecute API_JAM/distribuida/04_espejo_azure.sql en Azure.");
            }
            $columns[$table] = array_values(array_intersect($cloudColumns, self::columns($central, $table)));
            $keys[$table] = self::primaryKey($cloud, $table);
            if (in_array('updated_at', $columns[$table], true)) {
                $hasUpdatedAt[] = $table;
            }
        }

        $centralStats = self::tableStats($central, self::TABLES, $hasUpdatedAt);
        $cloudStats = self::tableStats($cloud, self::TABLES);

        $marks = [];
        foreach (DistributedService::query($cloud, 'SELECT table_name, rows_copied, CONVERT(VARCHAR(23), watermark, 121) AS watermark FROM dbo.mirror_sync') as $m) {
            $marks[$m['table_name']] = $m;
        }

        $result = [];
        foreach (self::TABLES as $table) {
            $cols = $columns[$table];
            $quoted = implode(', ', array_map([self::class, 'quote'], $cols));
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
                    $rows = DistributedService::query($central, "SELECT $quoted FROM dbo." . self::quote($table) . ' WHERE updated_at >= :w', [':w' => $watermark]);
                    self::deleteKeys($cloud, $table, $keys[$table], $rows);
                    self::insert($cloud, $table, $cols, $rows);

                    $count = (int)DistributedService::query($cloud, 'SELECT COUNT(*) AS n FROM dbo.' . self::quote($table))[0]['n'];
                    $item['mode'] = 'cambios';
                    if ($count !== $source['rows']) {
                        $incremental = false;
                    }
                }
                if (!$incremental) {
                    $rows = DistributedService::query($central, "SELECT $quoted FROM dbo." . self::quote($table));
                    $cloud->exec('DELETE FROM dbo.' . self::quote($table));
                    self::insert($cloud, $table, $cols, $rows);
                    $item['mode'] = 'completa';
                }
                self::saveMark($cloud, $table, $source['rows'], $source['max_updated']);
                $cloud->commit();
            } catch (Throwable $e) {
                if ($cloud->inTransaction()) {
                    $cloud->rollBack();
                }
                throw new RuntimeException("Copia de $table: " . DistributedService::errorText($e), 0, $e);
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

    /* Last copy to the mirror: the most recent date in dbo.mirror_sync. */
    public static function lastCopy(PDO $cloud): ?string
    {
        try {
            $row = DistributedService::query($cloud, 'SELECT CONVERT(VARCHAR(19), MAX(synced_at), 120) AS last_copy FROM dbo.mirror_sync');
            return $row[0]['last_copy'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
