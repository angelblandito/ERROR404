<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Database;
use Apicore\Core\Settings;
use PDO;
use RuntimeException;
use Throwable;
use ValueError;

/**
 * Endpoints of the distributed database.
 *
 *   GET  /nodes                    Status of every node (the Azure mirror included)
 *   GET  /distributed/inventory    Inventory rebuilt from the horizontal fragments
 *   GET  /distributed/orders       Orders rebuilt from the horizontal fragments
 *   GET  /distributed/customers    Customers rebuilt from the vertical fragments
 *   GET  /distributed/fragments    Completeness of every fragment against the central node
 *   GET  /replication/status       Published catalog compared against every subscriber
 *   POST /distributed/sync         Runs dist.sp_sincronizar_nodos on the central node
 *
 * The node filter is sent as ?node=tijuana|cdmx|online.
 *
 * Failure transparency: when a fragment node does not answer, its data is read
 * from another copy with the same predicate id_store = N, in this order:
 *   1. Azure SQL mirror (cloud node, when configured)
 *   2. central node JAM_db
 * The "source" field of the answer says where the data came from:
 *   fragmento | espejo:cloud | respaldo:central | sin_datos
 * Changes made while the node was down stay on the central node; as soon as
 * the node answers again, GET /nodes brings it up to date.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class DistributedService
{
    public const FRAGMENTS = [
        'tijuana' => ['id_store' => 1, 'label' => 'Sucursal Tijuana'],
        'cdmx' => ['id_store' => 2, 'label' => 'Sucursal CDMX'],
        'online' => ['id_store' => 4, 'label' => 'Tienda en línea'],
    ];

    public const FRAGMENT_TABLES = ['employee', 'inventory', 'customer_order', 'order_item', 'payment'];

    public const REPLICATED_TABLES = [
        'role', 'store_type', 'category', 'size', 'color', 'loyalty_level', 'account_tier', 'absence_type',
        'state', 'season_theme', 'store', 'store_config', 'product', 'product_variant', 'promotion',
    ];

    public const SUBSCRIBERS = ['tijuana', 'cdmx', 'online', 'cloud'];

    public static function query(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function substr(string $text, int $start, ?int $length = null): string
    {
        return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
    }

    public static function strlen(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    public static function errorText(Throwable $e): string
    {
        $text = $e->getMessage();
        $pos = strrpos($text, ']');
        if ($pos !== false) {
            $text = trim(substr($text, $pos + 1));
        }
        // No server file paths in the message
        $text = (string)preg_replace('/,? called in \S+ on line \d+/', '', $text);
        $text = (string)preg_replace('#(?:[A-Za-z]:)?[\\\\/][^\s:]*[\\\\/]([\w.-]+\.php)#', '$1', $text);
        return self::substr($text, 0, 200);
    }

    public static function selectedFragments(?string $filter): array
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return self::FRAGMENTS;
        }
        if (!array_key_exists($filter, self::FRAGMENTS)) {
            throw new ValueError('Nodo no válido. Use: ' . implode(', ', array_keys(self::FRAGMENTS)));
        }
        return [$filter => self::FRAGMENTS[$filter]];
    }

    /* Full copies that can answer for a fragment that is down, in order of preference. */
    public static function backupNodes(): array
    {
        $nodes = Database::config();
        $backups = [];
        foreach (['cloud', Settings::instance()->JAM_DEFAULT_NODE, 'central'] as $name) {
            if (isset($nodes[$name]) && !in_array($name, $backups, true)) {
                $backups[] = $name;
            }
        }
        return $backups;
    }

    public static function sourceLabel(string $node): string
    {
        return ($node === 'cloud' ? 'espejo:' : 'respaldo:') . $node;
    }

    /*
     * Run a query on the first copy that answers.
     * Returns [rows, node] or throws the last error.
     */
    public static function queryBackup(string $sql, array $params = []): array
    {
        $last = null;
        foreach (self::backupNodes() as $backup) {
            try {
                return [self::query(Database::node($backup), $sql, $params), $backup];
            } catch (Throwable $e) {
                $last = $e;
            }
        }
        throw $last ?? new RuntimeException('No hay copias configuradas.');
    }

    /*
     * Read a query from the fragment node. When the node does not answer, run
     * the fallback query (with :id_store) on the mirror or on the central node.
     * Returns [rows, source, error].
     */
    public static function readWithFallback(string $node, string $fragmentSql, string $fallbackSql, int $idStore): array
    {
        try {
            $rows = self::query(Database::node($node), $fragmentSql);
            return [$rows, 'fragmento', null];
        } catch (Throwable $fragmentError) {
            try {
                [$rows, $backup] = self::queryBackup($fallbackSql, [':id_store' => $idStore]);
                return [$rows, self::sourceLabel($backup), self::errorText($fragmentError)];
            } catch (Throwable $fallbackError) {
                return [[], 'sin_datos', self::errorText($fragmentError)];
            }
        }
    }

    /* Which copy serves the reads of a fragment right now. */
    public static function servedBy(string $node): ?string
    {
        try {
            Database::node($node);
            return 'fragmento';
        } catch (Throwable $e) {
            foreach (self::backupNodes() as $backup) {
                try {
                    Database::node($backup);
                    return self::sourceLabel($backup);
                } catch (Throwable $ignored) {
                }
            }
        }
        return null;
    }

    /* Sync status of every fragment, stored in JAM_db.dist.node_sync. */
    public static function nodeSyncInfo(bool $refresh = false): array
    {
        static $info = null;
        if ($info !== null && !$refresh) {
            return $info;
        }
        $info = [];
        try {
            $rows = self::query(Database::node('central'), '
                SELECT node_name, pending,
                       CONVERT(VARCHAR(19), last_success, 120) AS last_success,
                       CONVERT(VARCHAR(19), last_attempt, 120) AS last_attempt,
                       last_error
                FROM dist.node_sync');
            foreach ($rows as $r) {
                $info[$r['node_name']] = [
                    'pending' => (bool)$r['pending'],
                    'last_success' => $r['last_success'],
                    'last_attempt' => $r['last_attempt'],
                    'last_error' => $r['last_error'],
                ];
            }
        } catch (Throwable $e) {
            error_log('dist.node_sync no disponible: ' . $e->getMessage());
        }
        return $info;
    }

    /*
     * A node left pending because it was down is synchronized again once it
     * answers. Returns the nodes that were brought up to date.
     */
    public static function catchUp(): array
    {
        $ready = [];
        foreach (self::nodeSyncInfo() as $node => $state) {
            if (!$state['pending']) {
                continue;
            }
            try {
                Database::node($node);
                $ready[] = $node;
            } catch (Throwable $e) {
            }
        }
        if (!$ready) {
            return [];
        }

        try {
            self::sync();
        } catch (Throwable $e) {
            error_log('Puesta al día de nodos: ' . $e->getMessage());
            return [];
        }

        $after = self::nodeSyncInfo(true);
        return array_values(array_filter($ready, function ($node) use ($after) {
            return empty($after[$node]['pending']);
        }));
    }

    /* Catalog (variants and stores) read from the default node to enrich the fragments. */
    public static function catalog(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = ['variants' => [], 'stores' => []];
        try {
            [$variants] = self::queryBackup('
                SELECT pv.id_variant, pv.sku, p.id_product, p.name AS product_name, p.base_price,
                       s.code AS size, c.name AS color
                FROM dbo.product_variant AS pv
                JOIN dbo.product AS p ON p.id_product = pv.id_product
                JOIN dbo.size AS s ON s.id_size = pv.id_size
                JOIN dbo.color AS c ON c.id_color = pv.id_color');
            foreach ($variants as $v) {
                $catalog['variants'][(int)$v['id_variant']] = $v;
            }
            [$stores] = self::queryBackup('SELECT id_store, store_name FROM dbo.store');
            foreach ($stores as $s) {
                $catalog['stores'][(int)$s['id_store']] = $s['store_name'];
            }
        } catch (Throwable $e) {
            error_log('Catálogo no disponible: ' . $e->getMessage());
        }
        return $catalog;
    }

    public static function variantFields(int $idVariant): array
    {
        $v = self::catalog()['variants'][$idVariant] ?? null;
        return [
            'sku' => $v['sku'] ?? null,
            'id_product' => isset($v['id_product']) ? (int)$v['id_product'] : null,
            'product_name' => $v['product_name'] ?? null,
            'size' => $v['size'] ?? null,
            'color' => $v['color'] ?? null,
            'base_price' => $v['base_price'] ?? null,
        ];
    }

    public static function nodesStatus(): array
    {
        $caughtUp = self::catchUp();
        $syncInfo = self::nodeSyncInfo();

        $result = [];
        foreach (Database::config() as $name => $cfg) {
            $item = [
                'node' => $name,
                'label' => $cfg['label'],
                'role' => $cfg['role'],
                'database' => $cfg['database'],
                'id_store' => $cfg['id_store'],
                'is_default' => $name === Settings::instance()->JAM_DEFAULT_NODE,
                'status' => 'sin_conexion',
                'endpoint' => null,
                'server' => $cfg['server'],
                'latency_ms' => null,
                'error' => null,
                'sync' => $syncInfo[$name] ?? null,
                'caught_up' => in_array($name, $caughtUp, true),
                'served_by' => null,
            ];

            try {
                $pdo = Database::node($name);
                $info = Database::info($name);
                $item['status'] = 'en_linea';
                $item['endpoint'] = $info['endpoint'];
                $item['server'] = $info['server'];
                $item['latency_ms'] = $info['latency_ms'];

                $row = self::query($pdo, "SELECT @@SERVERNAME AS server_name,
                                                CAST(DATABASEPROPERTYEX(DB_NAME(), 'Updateability') AS VARCHAR(20)) AS updateability")[0];
                $item['server_name'] = $row['server_name'];
                $item['updateability'] = $row['updateability'];

                if ($name === 'cloud') {
                    $item['last_copy'] = MirrorService::lastCopy($pdo);
                    try {
                        $item['geo_replication'] = self::query($pdo, '
                            SELECT partner_server, partner_database, role_desc, replication_state_desc,
                                   replication_lag_sec, last_replication
                            FROM sys.dm_geo_replication_link_status');
                    } catch (Throwable $e) {
                        $item['geo_replication'] = [];
                    }
                }
            } catch (Throwable $e) {
                $info = Database::info($name);
                $item['latency_ms'] = $info['latency_ms'] ?? null;
                $item['error'] = self::errorText($e);
            }

            if (isset(self::FRAGMENTS[$name])) {
                $item['served_by'] = self::servedBy($name);
            }

            $result[] = $item;
        }
        return $result;
    }

    public static function inventory(?string $filter): array
    {
        $rows = [];
        $sources = [];

        foreach (self::selectedFragments($filter) as $node => $fragment) {
            $columns = 'id_inventory, id_store, id_variant, stock, min_stock, updated_at';
            [$data, $source, $error] = self::readWithFallback(
                $node,
                "SELECT $columns FROM dbo.inventory WHERE deleted = 0",
                "SELECT $columns FROM dbo.inventory WHERE deleted = 0 AND id_store = :id_store",
                $fragment['id_store']
            );
            $sources[$node] = ['source' => $source, 'error' => $error, 'rows' => count($data)];

            foreach ($data as $r) {
                $rows[] = array_merge([
                    'node' => $node,
                    'node_label' => $fragment['label'],
                    'id_inventory' => (int)$r['id_inventory'],
                    'id_store' => (int)$r['id_store'],
                    'store_name' => self::catalog()['stores'][(int)$r['id_store']] ?? null,
                    'id_variant' => (int)$r['id_variant'],
                    'stock' => (int)$r['stock'],
                    'min_stock' => (int)$r['min_stock'],
                    'low_stock' => (int)$r['stock'] <= (int)$r['min_stock'],
                    'updated_at' => $r['updated_at'],
                ], self::variantFields((int)$r['id_variant']));
            }
        }

        return ['rows' => $rows, 'sources' => $sources];
    }

    public static function orders(?string $filter): array
    {
        $rows = [];
        $sources = [];

        foreach (self::selectedFragments($filter) as $node => $fragment) {
            $orderColumns = 'id_order, id_store, id_customer, id_employee, id_promotion, order_date, status, channel, subtotal, discount, total, notes';
            [$orders, $source, $error] = self::readWithFallback(
                $node,
                "SELECT $orderColumns FROM dbo.customer_order WHERE deleted = 0",
                "SELECT $orderColumns FROM dbo.customer_order WHERE deleted = 0 AND id_store = :id_store",
                $fragment['id_store']
            );

            [$items] = self::readWithFallback(
                $node,
                'SELECT id_item, id_order, id_variant, quantity, unit_price FROM dbo.order_item WHERE deleted = 0',
                'SELECT oi.id_item, oi.id_order, oi.id_variant, oi.quantity, oi.unit_price
                 FROM dbo.order_item AS oi
                 JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
                 WHERE oi.deleted = 0 AND co.id_store = :id_store',
                $fragment['id_store']
            );

            $itemsByOrder = [];
            foreach ($items as $it) {
                $itemsByOrder[(int)$it['id_order']][] = array_merge([
                    'id_item' => (int)$it['id_item'],
                    'id_variant' => (int)$it['id_variant'],
                    'quantity' => (int)$it['quantity'],
                    'unit_price' => $it['unit_price'],
                ], self::variantFields((int)$it['id_variant']));
            }

            $sources[$node] = ['source' => $source, 'error' => $error, 'rows' => count($orders)];

            foreach ($orders as $o) {
                $o['node'] = $node;
                $o['node_label'] = $fragment['label'];
                $o['store_name'] = self::catalog()['stores'][(int)$o['id_store']] ?? null;
                $o['items'] = $itemsByOrder[(int)$o['id_order']] ?? [];
                $rows[] = $o;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)$b['order_date'], (string)$a['order_date']);
        });

        return ['rows' => $rows, 'sources' => $sources];
    }

    public static function maskEmail(?string $email): ?string
    {
        if (!$email || strpos($email, '@') === false) {
            return $email;
        }
        [$user, $domain] = explode('@', $email, 2);
        return self::substr($user, 0, 2) . str_repeat('*', max(1, self::strlen($user) - 2)) . '@' . $domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 4) {
            return $phone;
        }
        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }

    /*
     * Vertical fragmentation of customer:
     *   customer_profile  in JAM_online  (profile)
     *   customer_private  in JAM_db.dist (personal data)
     * The relation is rebuilt with a JOIN on id_customer.
     */
    public static function customers(): array
    {
        $profileColumns = ['id_customer', 'id_loyalty_level', 'id_account_tier', 'id_store', 'first_name', 'last_name_paternal', 'last_name_maternal', 'accepts_marketing', 'customer_since'];
        $privateColumns = ['id_customer', 'id_address', 'birth_date', 'sex', 'phone', 'email'];

        $fragments = [
            'customer_profile' => ['node' => 'online', 'table' => 'JAM_online.dbo.customer_profile', 'columns' => $profileColumns, 'status' => 'ok', 'error' => null],
            'customer_private' => ['node' => 'central', 'table' => 'JAM_db.dist.customer_private', 'columns' => $privateColumns, 'status' => 'ok', 'error' => null],
        ];

        $profiles = [];
        $private = [];

        try {
            $profiles = self::query(Database::node('online'),
                'SELECT ' . implode(', ', $profileColumns) . ' FROM dbo.customer_profile WHERE deleted = 0');
        } catch (Throwable $e) {
            $fragments['customer_profile']['status'] = 'sin_conexion';
            $fragments['customer_profile']['error'] = self::errorText($e);
        }

        try {
            $private = self::query(Database::node('central'),
                'SELECT ' . implode(', ', $privateColumns) . ' FROM dist.customer_private');
        } catch (Throwable $e) {
            $fragments['customer_private']['status'] = 'sin_conexion';
            $fragments['customer_private']['error'] = self::errorText($e);
        }

        $source = 'fragmentos';

        if ($fragments['customer_profile']['status'] !== 'ok' || $fragments['customer_private']['status'] !== 'ok') {
            try {
                [$all, $backup] = self::queryBackup('
                    SELECT id_customer, id_loyalty_level, id_account_tier, id_store, id_address, first_name, last_name_paternal,
                           last_name_maternal, birth_date, sex, phone, email, accepts_marketing, customer_since
                    FROM dbo.customer WHERE deleted = 0');
                $profiles = [];
                $private = [];
                foreach ($all as $c) {
                    $profiles[] = array_intersect_key($c, array_flip($profileColumns));
                    $private[] = array_intersect_key($c, array_flip($privateColumns));
                }
                $source = self::sourceLabel($backup);
            } catch (Throwable $e) {
                $source = 'sin_datos';
            }
        }

        $privateById = [];
        foreach ($private as $p) {
            $privateById[(int)$p['id_customer']] = $p;
        }

        $rows = [];
        foreach ($profiles as $p) {
            $v = $privateById[(int)$p['id_customer']] ?? null;
            $rows[] = [
                'id_customer' => (int)$p['id_customer'],
                'first_name' => $p['first_name'],
                'last_name_paternal' => $p['last_name_paternal'],
                'last_name_maternal' => $p['last_name_maternal'],
                'id_loyalty_level' => (int)$p['id_loyalty_level'],
                'id_account_tier' => (int)$p['id_account_tier'],
                'customer_since' => $p['customer_since'],
                'id_store' => (int)$p['id_store'],
                'store_name' => self::catalog()['stores'][(int)$p['id_store']] ?? null,
                'accepts_marketing' => (bool)$p['accepts_marketing'],
                'birth_date' => $v['birth_date'] ?? null,
                'sex' => $v['sex'] ?? null,
                'phone' => self::maskPhone($v['phone'] ?? null),
                'email' => self::maskEmail($v['email'] ?? null),
                'reconstructed' => $v !== null,
            ];
        }

        return ['rows' => $rows, 'fragments' => $fragments, 'source' => $source];
    }

    /* Compare every fragment with what the central node says it should hold. */
    public static function fragmentsSummary(): array
    {
        $expectedSql = [
            'employee' => 'SELECT COUNT(*) AS n FROM dbo.employee WHERE id_store = :id_store',
            'inventory' => 'SELECT COUNT(*) AS n FROM dbo.inventory WHERE id_store = :id_store',
            'customer_order' => 'SELECT COUNT(*) AS n FROM dbo.customer_order WHERE id_store = :id_store',
            'order_item' => 'SELECT COUNT(*) AS n FROM dbo.order_item AS oi JOIN dbo.customer_order AS co ON co.id_order = oi.id_order WHERE co.id_store = :id_store',
            'payment' => 'SELECT COUNT(*) AS n FROM dbo.payment AS p JOIN dbo.customer_order AS co ON co.id_order = p.id_order WHERE co.id_store = :id_store',
        ];

        /* The expected rows come from the central node; if it does not answer, from the mirror. */
        $central = null;
        foreach (array_unique(array_merge(['central'], self::backupNodes())) as $backup) {
            try {
                $central = Database::node($backup);
                break;
            } catch (Throwable $e) {
                $central = null;
            }
        }

        $syncInfo = self::nodeSyncInfo();

        $result = [];
        foreach (self::FRAGMENTS as $node => $fragment) {
            $nodeItem = [
                'node' => $node,
                'label' => $fragment['label'],
                'predicate' => 'id_store = ' . $fragment['id_store'],
                'status' => 'en_linea',
                'error' => null,
                'served_by' => self::servedBy($node),
                'sync' => $syncInfo[$node] ?? null,
                'tables' => [],
            ];

            $pdo = null;
            try {
                $pdo = Database::node($node);
            } catch (Throwable $e) {
                $nodeItem['status'] = 'sin_conexion';
                $nodeItem['error'] = self::errorText($e);
            }

            foreach (self::FRAGMENT_TABLES as $table) {
                $rows = null;
                $expected = null;
                if ($pdo) {
                    try {
                        $rows = (int)self::query($pdo, "SELECT COUNT(*) AS n FROM dbo.$table")[0]['n'];
                    } catch (Throwable $e) {
                        $rows = null;
                    }
                }
                if ($central) {
                    try {
                        $expected = (int)self::query($central, $expectedSql[$table], [':id_store' => $fragment['id_store']])[0]['n'];
                    } catch (Throwable $e) {
                        $expected = null;
                    }
                }
                $nodeItem['tables'][] = [
                    'table' => $table,
                    'type' => in_array($table, ['order_item', 'payment'], true) ? 'derivada' : 'primaria',
                    'rows' => $rows,
                    'expected' => $expected,
                    'complete' => $rows !== null && $expected !== null && $rows === $expected,
                ];
            }
            $result[] = $nodeItem;
        }
        return $result;
    }

    /* Compare count and checksum of every published table against every subscriber. */
    public static function replicationStatus(): array
    {
        /* Comparable columns: rowversion (system_type_id 189) is left out, because
           every server generates its own values and the checksum would never match. */
        $columnsOf = function (PDO $pdo, string $table): array {
            $rows = self::query($pdo,
                'SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(:t) AND system_type_id <> 189 ORDER BY column_id',
                [':t' => 'dbo.' . $table]);
            return array_map(function ($r) {
                return '[' . str_replace(']', ']]', $r['name']) . ']';
            }, $rows);
        };

        $checksumSql = function (string $table, array $columns): string {
            return 'SELECT COUNT(*) AS n, CHECKSUM_AGG(BINARY_CHECKSUM(' . implode(', ', $columns) . ")) AS chk FROM dbo.$table";
        };

        try {
            $central = Database::node('central');
        } catch (Throwable $e) {
            throw new RuntimeException('El nodo central (publicador) no responde: ' . self::errorText($e));
        }

        $publisher = [];
        $columns = [];
        foreach (self::REPLICATED_TABLES as $table) {
            try {
                $columns[$table] = $columnsOf($central, $table);
                $publisher[$table] = $columns[$table]
                    ? self::query($central, $checksumSql($table, $columns[$table]))[0]
                    : null;
            } catch (Throwable $e) {
                $publisher[$table] = null;
            }
        }

        $enabled = Database::config();
        $subscribers = [];
        foreach (self::SUBSCRIBERS as $node) {
            $item = ['node' => $node, 'status' => 'sin_conexion', 'error' => null, 'tables' => []];
            if (!isset($enabled[$node])) {
                $item['status'] = 'no_configurado';
                $subscribers[] = $item;
                continue;
            }
            try {
                $pdo = Database::node($node);
                $item['status'] = 'en_linea';
            } catch (Throwable $e) {
                $item['error'] = self::errorText($e);
                $subscribers[] = $item;
                continue;
            }

            foreach (self::REPLICATED_TABLES as $table) {
                $state = 'sin_replicar';
                $rows = null;
                try {
                    if (empty($columns[$table])) {
                        throw new RuntimeException('Tabla sin columnas en el publicador');
                    }
                    $sub = self::query($pdo, $checksumSql($table, $columns[$table]))[0];
                    $rows = (int)$sub['n'];
                    $pub = $publisher[$table];
                    $state = ($pub !== null && (int)$pub['n'] === $rows && (string)$pub['chk'] === (string)$sub['chk'])
                        ? 'sincronizado'
                        : 'diferente';
                } catch (Throwable $e) {
                    $state = 'sin_replicar';
                }
                $item['tables'][] = [
                    'table' => $table,
                    'publisher_rows' => $publisher[$table] !== null ? (int)$publisher[$table]['n'] : null,
                    'subscriber_rows' => $rows,
                    'state' => $state,
                ];
            }
            $subscribers[] = $item;
        }

        return ['publisher' => 'central', 'publications' => ['pub_catalogo' => ['tijuana', 'cdmx', 'online'], 'pub_completa' => ['cloud']], 'subscribers' => $subscribers];
    }

    public static function sync(): array
    {
        $pdo = Database::node('central');
        $stmt = $pdo->query('EXEC dist.sp_sincronizar_nodos');
        $row = null;
        do {
            if ($stmt->columnCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            }
        } while ($stmt->nextRowset());
        $stmt->closeCursor();

        $pending = [];
        foreach (self::nodeSyncInfo(true) as $node => $state) {
            if ($state['pending']) {
                $pending[$node] = $state['last_error'];
            }
        }

        return [
            'synchronized_at' => $row['synchronized_at'] ?? date('Y-m-d H:i:s'),
            'pending' => $pending,
        ];
    }

    /*
     * After every successful write (see SyncMiddleware): refresh the fragments
     * and copy the changes to the Azure mirror. A failure is logged and never
     * changes the answer the client already earned.
     */
    public static function afterWrite(): void
    {
        try {
            self::sync();
        } catch (Throwable $e) {
            error_log('Sincronización de fragmentos: ' . $e->getMessage());
        }

        if (!MirrorService::enabled()) {
            return;
        }
        try {
            MirrorService::copy(false);
        } catch (Throwable $e) {
            error_log('Copia al espejo: ' . $e->getMessage());
        }
    }
}
