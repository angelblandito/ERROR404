<?php
/*
 * Endpoints de la base de datos distribuida.
 *
 *   GET  /nodes                    Estado de cada nodo (incluye el espejo de Azure)
 *   GET  /distributed/inventory    Inventario reconstruido desde los fragmentos horizontales
 *   GET  /distributed/orders       Pedidos reconstruidos desde los fragmentos horizontales
 *   GET  /distributed/customers    Clientes reconstruidos desde los fragmentos verticales
 *   GET  /distributed/fragments    Completitud de cada fragmento contra el nodo central
 *   GET  /replication/status       Comparación del catálogo publicado contra cada suscriptor
 *   POST /distributed/sync         Ejecuta dist.sp_sincronizar_nodos en el nodo central
 *
 * Los filtros por nodo se envían como ?node=tijuana|cdmx|online.
 * Si un nodo fragmento no responde, sus datos se leen del nodo por defecto
 * (central o réplica en la nube) filtrando por id_store; la respuesta lo
 * indica en el campo "source".
 */

const DIST_FRAGMENTS = [
    'tijuana' => ['id_store' => 1, 'label' => 'Sucursal Tijuana'],
    'cdmx' => ['id_store' => 2, 'label' => 'Sucursal CDMX'],
    'online' => ['id_store' => 4, 'label' => 'Tienda en línea'],
];

const DIST_FRAGMENT_TABLES = ['employee', 'inventory', 'customer_order', 'order_item', 'payment'];

const DIST_REPLICATED_TABLES = [
    'role', 'store_type', 'category', 'size', 'color', 'loyalty_level', 'account_tier', 'absence_type',
    'state', 'season_theme', 'store', 'store_config', 'product', 'product_variant', 'promotion',
];

const DIST_SUBSCRIBERS = ['tijuana', 'cdmx', 'online', 'cloud'];

function dist_respond(int $status, $data, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $status < 400,
        'data' => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function dist_query(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dist_substr(string $text, int $start, ?int $length = null): string
{
    return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
}

function dist_strlen(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
}

function dist_error_text(Throwable $e): string
{
    $text = $e->getMessage();
    $pos = strrpos($text, ']');
    if ($pos !== false) {
        $text = trim(substr($text, $pos + 1));
    }
    return dist_substr($text, 0, 200);
}

function dist_selected_fragments(?string $filter): array
{
    if ($filter === null || $filter === '' || $filter === 'all') {
        return DIST_FRAGMENTS;
    }
    if (!array_key_exists($filter, DIST_FRAGMENTS)) {
        throw new InvalidArgumentException('Nodo no válido. Use: ' . implode(', ', array_keys(DIST_FRAGMENTS)));
    }
    return [$filter => DIST_FRAGMENTS[$filter]];
}

/*
 * Lee una consulta en el nodo fragmento. Si el nodo no responde, ejecuta la
 * consulta de respaldo en el nodo por defecto.
 * Devuelve [renglones, origen, error].
 */
function dist_read_with_fallback(string $node, string $fragmentSql, string $fallbackSql, int $idStore): array
{
    try {
        $rows = dist_query(jam_node($node), $fragmentSql);
        return [$rows, 'fragmento', null];
    } catch (Throwable $fragmentError) {
        try {
            $rows = dist_query(jam_node(JAM_DEFAULT_NODE), $fallbackSql, [':id_store' => $idStore]);
            return [$rows, 'respaldo:' . JAM_DEFAULT_NODE, dist_error_text($fragmentError)];
        } catch (Throwable $fallbackError) {
            return [[], 'sin_datos', dist_error_text($fragmentError)];
        }
    }
}

/* Catálogo (variantes y tiendas) leído del nodo por defecto para enriquecer los fragmentos. */
function dist_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = ['variants' => [], 'stores' => []];
    try {
        $pdo = jam_node(JAM_DEFAULT_NODE);
        $variants = dist_query($pdo, '
            SELECT pv.id_variant, pv.sku, p.id_product, p.name AS product_name, p.base_price,
                   s.code AS size, c.name AS color
            FROM dbo.product_variant AS pv
            JOIN dbo.product AS p ON p.id_product = pv.id_product
            JOIN dbo.size AS s ON s.id_size = pv.id_size
            JOIN dbo.color AS c ON c.id_color = pv.id_color');
        foreach ($variants as $v) {
            $catalog['variants'][(int)$v['id_variant']] = $v;
        }
        foreach (dist_query($pdo, 'SELECT id_store, store_name FROM dbo.store') as $s) {
            $catalog['stores'][(int)$s['id_store']] = $s['store_name'];
        }
    } catch (Throwable $e) {
        error_log('Catálogo no disponible: ' . $e->getMessage());
    }
    return $catalog;
}

function dist_variant_fields(int $idVariant): array
{
    $v = dist_catalog()['variants'][$idVariant] ?? null;
    return [
        'sku' => $v['sku'] ?? null,
        'id_product' => isset($v['id_product']) ? (int)$v['id_product'] : null,
        'product_name' => $v['product_name'] ?? null,
        'size' => $v['size'] ?? null,
        'color' => $v['color'] ?? null,
        'base_price' => $v['base_price'] ?? null,
    ];
}

function dist_nodes_status(): array
{
    $result = [];
    foreach (jam_nodes_config() as $name => $cfg) {
        $item = [
            'node' => $name,
            'label' => $cfg['label'],
            'role' => $cfg['role'],
            'database' => $cfg['database'],
            'id_store' => $cfg['id_store'],
            'is_default' => $name === JAM_DEFAULT_NODE,
            'status' => 'sin_conexion',
            'endpoint' => null,
            'server' => $cfg['server'],
            'latency_ms' => null,
            'error' => null,
        ];

        try {
            $pdo = jam_node($name);
            $info = jam_node_info($name);
            $item['status'] = 'en_linea';
            $item['endpoint'] = $info['endpoint'];
            $item['server'] = $info['server'];
            $item['latency_ms'] = $info['latency_ms'];

            $row = dist_query($pdo, "SELECT @@SERVERNAME AS server_name,
                                            CAST(DATABASEPROPERTYEX(DB_NAME(), 'Updateability') AS VARCHAR(20)) AS updateability")[0];
            $item['server_name'] = $row['server_name'];
            $item['updateability'] = $row['updateability'];

            if ($name === 'cloud') {
                try {
                    $item['geo_replication'] = dist_query($pdo, '
                        SELECT partner_server, partner_database, role_desc, replication_state_desc,
                               replication_lag_sec, last_replication
                        FROM sys.dm_geo_replication_link_status');
                } catch (Throwable $e) {
                    $item['geo_replication'] = [];
                }
            }
        } catch (Throwable $e) {
            $info = jam_node_info($name);
            $item['latency_ms'] = $info['latency_ms'] ?? null;
            $item['error'] = dist_error_text($e);
        }

        $result[] = $item;
    }
    return $result;
}

function dist_inventory(?string $filter): array
{
    $rows = [];
    $sources = [];

    foreach (dist_selected_fragments($filter) as $node => $fragment) {
        $columns = 'id_inventory, id_store, id_variant, stock, min_stock, updated_at';
        [$data, $source, $error] = dist_read_with_fallback(
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
                'store_name' => dist_catalog()['stores'][(int)$r['id_store']] ?? null,
                'id_variant' => (int)$r['id_variant'],
                'stock' => (int)$r['stock'],
                'min_stock' => (int)$r['min_stock'],
                'low_stock' => (int)$r['stock'] <= (int)$r['min_stock'],
                'updated_at' => $r['updated_at'],
            ], dist_variant_fields((int)$r['id_variant']));
        }
    }

    return ['rows' => $rows, 'sources' => $sources];
}

function dist_orders(?string $filter): array
{
    $rows = [];
    $sources = [];

    foreach (dist_selected_fragments($filter) as $node => $fragment) {
        $orderColumns = 'id_order, id_store, id_customer, id_employee, id_promotion, order_date, status, channel, subtotal, discount, total, notes';
        [$orders, $source, $error] = dist_read_with_fallback(
            $node,
            "SELECT $orderColumns FROM dbo.customer_order WHERE deleted = 0",
            "SELECT $orderColumns FROM dbo.customer_order WHERE deleted = 0 AND id_store = :id_store",
            $fragment['id_store']
        );

        [$items] = dist_read_with_fallback(
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
            ], dist_variant_fields((int)$it['id_variant']));
        }

        $sources[$node] = ['source' => $source, 'error' => $error, 'rows' => count($orders)];

        foreach ($orders as $o) {
            $o['node'] = $node;
            $o['node_label'] = $fragment['label'];
            $o['store_name'] = dist_catalog()['stores'][(int)$o['id_store']] ?? null;
            $o['items'] = $itemsByOrder[(int)$o['id_order']] ?? [];
            $rows[] = $o;
        }
    }

    usort($rows, function ($a, $b) {
        return strcmp((string)$b['order_date'], (string)$a['order_date']);
    });

    return ['rows' => $rows, 'sources' => $sources];
}

function dist_mask_email(?string $email): ?string
{
    if (!$email || strpos($email, '@') === false) {
        return $email;
    }
    [$user, $domain] = explode('@', $email, 2);
    return dist_substr($user, 0, 2) . str_repeat('*', max(1, dist_strlen($user) - 2)) . '@' . $domain;
}

function dist_mask_phone(?string $phone): ?string
{
    if (!$phone || strlen($phone) < 4) {
        return $phone;
    }
    return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
}

/*
 * Fragmentación vertical de customer:
 *   customer_profile  en JAM_online  (perfil)
 *   customer_private  en JAM_db.dist (datos personales)
 * La relación se reconstruye con un JOIN por id_customer.
 */
function dist_customers(): array
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
        $profiles = dist_query(jam_node('online'),
            'SELECT ' . implode(', ', $profileColumns) . ' FROM dbo.customer_profile WHERE deleted = 0');
    } catch (Throwable $e) {
        $fragments['customer_profile']['status'] = 'sin_conexion';
        $fragments['customer_profile']['error'] = dist_error_text($e);
    }

    try {
        $private = dist_query(jam_node('central'),
            'SELECT ' . implode(', ', $privateColumns) . ' FROM dist.customer_private');
    } catch (Throwable $e) {
        $fragments['customer_private']['status'] = 'sin_conexion';
        $fragments['customer_private']['error'] = dist_error_text($e);
    }

    $source = 'fragmentos';

    if ($fragments['customer_profile']['status'] !== 'ok' || $fragments['customer_private']['status'] !== 'ok') {
        try {
            $all = dist_query(jam_node(JAM_DEFAULT_NODE), '
                SELECT id_customer, id_loyalty_level, id_account_tier, id_store, id_address, first_name, last_name_paternal,
                       last_name_maternal, birth_date, sex, phone, email, accepts_marketing, customer_since
                FROM dbo.customer WHERE deleted = 0');
            $profiles = [];
            $private = [];
            foreach ($all as $c) {
                $profiles[] = array_intersect_key($c, array_flip($profileColumns));
                $private[] = array_intersect_key($c, array_flip($privateColumns));
            }
            $source = 'respaldo:' . JAM_DEFAULT_NODE;
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
            'store_name' => dist_catalog()['stores'][(int)$p['id_store']] ?? null,
            'accepts_marketing' => (bool)$p['accepts_marketing'],
            'birth_date' => $v['birth_date'] ?? null,
            'sex' => $v['sex'] ?? null,
            'phone' => dist_mask_phone($v['phone'] ?? null),
            'email' => dist_mask_email($v['email'] ?? null),
            'reconstructed' => $v !== null,
        ];
    }

    return ['rows' => $rows, 'fragments' => $fragments, 'source' => $source];
}

/* Compara cada fragmento con lo que le corresponde según el nodo central. */
function dist_fragments_summary(): array
{
    $expectedSql = [
        'employee' => 'SELECT COUNT(*) AS n FROM dbo.employee WHERE id_store = :id_store',
        'inventory' => 'SELECT COUNT(*) AS n FROM dbo.inventory WHERE id_store = :id_store',
        'customer_order' => 'SELECT COUNT(*) AS n FROM dbo.customer_order WHERE id_store = :id_store',
        'order_item' => 'SELECT COUNT(*) AS n FROM dbo.order_item AS oi JOIN dbo.customer_order AS co ON co.id_order = oi.id_order WHERE co.id_store = :id_store',
        'payment' => 'SELECT COUNT(*) AS n FROM dbo.payment AS p JOIN dbo.customer_order AS co ON co.id_order = p.id_order WHERE co.id_store = :id_store',
    ];

    $central = null;
    try {
        $central = jam_node('central');
    } catch (Throwable $e) {
        $central = null;
    }

    $result = [];
    foreach (DIST_FRAGMENTS as $node => $fragment) {
        $nodeItem = [
            'node' => $node,
            'label' => $fragment['label'],
            'predicate' => 'id_store = ' . $fragment['id_store'],
            'status' => 'en_linea',
            'error' => null,
            'tables' => [],
        ];

        $pdo = null;
        try {
            $pdo = jam_node($node);
        } catch (Throwable $e) {
            $nodeItem['status'] = 'sin_conexion';
            $nodeItem['error'] = dist_error_text($e);
        }

        foreach (DIST_FRAGMENT_TABLES as $table) {
            $rows = null;
            $expected = null;
            if ($pdo) {
                try {
                    $rows = (int)dist_query($pdo, "SELECT COUNT(*) AS n FROM dbo.$table")[0]['n'];
                } catch (Throwable $e) {
                    $rows = null;
                }
            }
            if ($central) {
                try {
                    $expected = (int)dist_query($central, $expectedSql[$table], [':id_store' => $fragment['id_store']])[0]['n'];
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

/* Compara conteo y checksum de cada tabla publicada contra cada suscriptor. */
function dist_replication_status(): array
{
    /* Columnas comparables: se excluye rowversion (system_type_id 189), porque
       cada servidor genera sus propios valores y el checksum nunca coincidiría. */
    $columnsOf = function (PDO $pdo, string $table): array {
        $rows = dist_query($pdo,
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
        $central = jam_node('central');
    } catch (Throwable $e) {
        throw new RuntimeException('El nodo central (publicador) no responde: ' . dist_error_text($e));
    }

    $publisher = [];
    $columns = [];
    foreach (DIST_REPLICATED_TABLES as $table) {
        try {
            $columns[$table] = $columnsOf($central, $table);
            $publisher[$table] = $columns[$table]
                ? dist_query($central, $checksumSql($table, $columns[$table]))[0]
                : null;
        } catch (Throwable $e) {
            $publisher[$table] = null;
        }
    }

    $enabled = jam_nodes_config();
    $subscribers = [];
    foreach (DIST_SUBSCRIBERS as $node) {
        $item = ['node' => $node, 'status' => 'sin_conexion', 'error' => null, 'tables' => []];
        if (!isset($enabled[$node])) {
            $item['status'] = 'no_configurado';
            $subscribers[] = $item;
            continue;
        }
        try {
            $pdo = jam_node($node);
            $item['status'] = 'en_linea';
        } catch (Throwable $e) {
            $item['error'] = dist_error_text($e);
            $subscribers[] = $item;
            continue;
        }

        foreach (DIST_REPLICATED_TABLES as $table) {
            $state = 'sin_replicar';
            $rows = null;
            try {
                if (empty($columns[$table])) {
                    throw new RuntimeException('Tabla sin columnas en el publicador');
                }
                $sub = dist_query($pdo, $checksumSql($table, $columns[$table]))[0];
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

function dist_sync(): array
{
    $pdo = jam_node('central');
    $stmt = $pdo->query('EXEC dist.sp_sincronizar_nodos');
    $row = null;
    do {
        if ($stmt->columnCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        }
    } while ($stmt->nextRowset());

    return ['synchronized_at' => $row['synchronized_at'] ?? date('Y-m-d H:i:s')];
}

/* ---------------------------------------------------------------------
   Enrutamiento de los endpoints distribuidos
   --------------------------------------------------------------------- */
function dist_handle(string $method, string $uri): bool
{
    $node = isset($_GET['node']) ? (string)$_GET['node'] : null;

    try {
        if ($method === 'GET' && $uri === '/nodes') {
            dist_respond(200, ['default_node' => JAM_DEFAULT_NODE, 'nodes' => dist_nodes_status()], 'Estado de los nodos');
        } elseif ($method === 'GET' && $uri === '/distributed/inventory') {
            dist_respond(200, dist_inventory($node), 'Inventario reconstruido desde los fragmentos');
        } elseif ($method === 'GET' && $uri === '/distributed/orders') {
            dist_respond(200, dist_orders($node), 'Pedidos reconstruidos desde los fragmentos');
        } elseif ($method === 'GET' && $uri === '/distributed/customers') {
            dist_respond(200, dist_customers(), 'Clientes reconstruidos desde los fragmentos verticales');
        } elseif ($method === 'GET' && $uri === '/distributed/fragments') {
            dist_respond(200, dist_fragments_summary(), 'Completitud de los fragmentos');
        } elseif ($method === 'GET' && $uri === '/replication/status') {
            dist_respond(200, dist_replication_status(), 'Estado de la replicación');
        } elseif ($method === 'POST' && $uri === '/distributed/sync') {
            dist_respond(200, dist_sync(), 'Nodos sincronizados');
        } else {
            return false;
        }
    } catch (InvalidArgumentException $e) {
        dist_respond(400, null, $e->getMessage());
    } catch (Throwable $e) {
        error_log('Error en endpoint distribuido ' . $uri . ': ' . $e->getMessage());
        dist_respond(503, null, dist_error_text($e));
    }
    return true;
}
