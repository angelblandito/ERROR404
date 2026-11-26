<?php
/*
 * Administración de la tienda (solo cuentas con account_tier.can_manage_store = 1).
 *
 *   GET    /admin/summary                  Indicadores, ventas por día (30 días), por sucursal y por categoría
 *   GET    /admin/customers                Clientes con tipo de cuenta, antigüedad y compras del año
 *   PUT    /admin/customers/{id}/tier      {tier: 'standard' | 'priority'}
 *   POST   /admin/customers/evaluate       Ejecuta dbo.sp_evaluar_clientes_prioritarios
 *   GET    /admin/orders?status=           Pedidos del nodo central, prioritarios primero
 *   PUT    /admin/orders/{id}/status       {status}; cancelar o devolver regresa el stock
 *   PUT    /admin/products/{id}            {base_price}
 *   PUT    /admin/inventory                {id_store, id_variant, stock}
 *   GET    /admin/tickets?status=          Cola de soporte ordenada por prioridad y plazo
 *   PUT    /admin/tickets/{id}             {response, status: 'answered' | 'closed'}
 *   GET    /admin/announcements            Todos los comunicados
 *   POST   /admin/announcements            {audience, title, body, starts_on, ends_on?}
 *   DELETE /admin/announcements/{id}       Baja lógica
 *   GET    /admin/emails                   Bandeja de correos simulada
 *   GET    /admin/market                   Precios de la competencia (ver models/market.php)
 *   POST   /admin/market
 *   PUT    /admin/market/{id}
 *   DELETE /admin/market/{id}
 *
 * Layer: services. Logica de negocio y SQL; sin HTTP. Las rutas viven en routers y los
 * controllers de controllers llaman a estas funciones con el actor de la sesion.
 */

const ADMIN_TIME_ZONE = 'America/Mexico_City';
const ADMIN_SQL_TIME_ZONE = 'Central Standard Time (Mexico)';
const ADMIN_CHART_DAYS = 30;

const ADMIN_ORDER_STATUS = ['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'returned'];

/* Cambios de estado permitidos para un pedido. */
const ADMIN_ORDER_FLOW = [
    'pending' => ['paid', 'cancelled'],
    'paid' => ['shipped', 'delivered', 'cancelled'],
    'shipped' => ['delivered', 'returned'],
    'delivered' => ['returned'],
    'cancelled' => [],
    'returned' => [],
];

function admin_date(array $body, string $field, bool $required): ?string
{
    $value = trim((string)($body[$field] ?? ''));
    if ($value === '' && !$required) {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new HttpError(400, 'La fecha debe tener el formato AAAA-MM-DD.');
    }
    return $value;
}

/* ---------------------------------------------------------------------
   Resumen
   --------------------------------------------------------------------- */
function admin_summary(PDO $pdo): array
{
    $sales = store_rows($pdo, "
        SELECT
            ISNULL(SUM(CASE WHEN co.order_date >= CAST(SYSUTCDATETIME() AS DATE) THEN co.total END), 0) AS sales_today,
            ISNULL(SUM(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) THEN co.total END), 0) AS sales_30_days,
            COUNT(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) THEN 1 END) AS orders_30_days,
            ISNULL(SUM(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) AND co.priority_shipping = 1
                            THEN co.total END), 0) AS priority_sales_30_days
        FROM dbo.customer_order AS co
        WHERE co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')")[0];

    $pending = store_rows($pdo, "
        SELECT COUNT(*) AS pending_orders,
               COUNT(CASE WHEN priority_shipping = 1 THEN 1 END) AS pending_priority
        FROM dbo.customer_order
        WHERE deleted = 0 AND status IN ('pending', 'paid')")[0];

    $tickets = store_rows($pdo, "
        SELECT COUNT(*) AS open_tickets,
               COUNT(CASE WHEN priority = 'high' THEN 1 END) AS open_high,
               COUNT(CASE WHEN respond_by < SYSUTCDATETIME() THEN 1 END) AS overdue
        FROM dbo.support_ticket
        WHERE deleted = 0 AND status = 'open'")[0];

    $tiers = store_rows($pdo, '
        SELECT t.code, t.name, COUNT(c.id_customer) AS customers
        FROM dbo.account_tier AS t
        LEFT JOIN dbo.customer AS c ON c.id_account_tier = t.id_account_tier AND c.deleted = 0
        GROUP BY t.id_account_tier, t.code, t.name
        ORDER BY t.id_account_tier');

    $top = store_rows($pdo, "
        SELECT TOP (5) p.id_product, p.name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM dbo.order_item AS oi
        JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
        JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
        JOIN dbo.product AS p ON p.id_product = pv.id_product
        WHERE oi.deleted = 0 AND co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')
          AND co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME())
        GROUP BY p.id_product, p.name
        ORDER BY units DESC, revenue DESC");

    $lowStock = store_rows($pdo, '
        SELECT COUNT(*) AS low_stock
        FROM dbo.inventory
        WHERE deleted = 0 AND stock <= min_stock')[0];

    return array_merge([
        'sales_today' => (float)$sales['sales_today'],
        'sales_30_days' => (float)$sales['sales_30_days'],
        'orders_30_days' => (int)$sales['orders_30_days'],
        'priority_sales_30_days' => (float)$sales['priority_sales_30_days'],
        'pending_orders' => (int)$pending['pending_orders'],
        'pending_priority' => (int)$pending['pending_priority'],
        'open_tickets' => (int)$tickets['open_tickets'],
        'open_high' => (int)$tickets['open_high'],
        'overdue_tickets' => (int)$tickets['overdue'],
        'low_stock' => (int)$lowStock['low_stock'],
        'tiers' => array_map(function ($t) {
            return ['code' => $t['code'], 'name' => $t['name'], 'customers' => (int)$t['customers']];
        }, $tiers),
        'top_products' => array_map(function ($p) {
            return ['id_product' => (int)$p['id_product'], 'name' => $p['name'], 'units' => (int)$p['units'], 'revenue' => (float)$p['revenue']];
        }, $top),
    ], admin_sales_charts($pdo));
}

/* Ventas de los últimos 30 días agrupadas por día (hora del centro de México),
   por sucursal y por categoría. Los días sin ventas aparecen con 0. */
function admin_sales_charts(PDO $pdo): array
{
    $localDate = "CAST(co.order_date AT TIME ZONE 'UTC' AT TIME ZONE '" . ADMIN_SQL_TIME_ZONE . "' AS DATE)";
    $since = "DATEADD(DAY, -" . (ADMIN_CHART_DAYS - 1) . ", CAST(SYSUTCDATETIME() AT TIME ZONE 'UTC' AT TIME ZONE '" . ADMIN_SQL_TIME_ZONE . "' AS DATE))";
    $valid = "co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned') AND $localDate >= $since";

    $daily = store_rows($pdo, "
        SELECT $localDate AS sale_day, SUM(co.total) AS total, COUNT(*) AS orders
        FROM dbo.customer_order AS co
        WHERE $valid
        GROUP BY $localDate");
    $byDay = [];
    foreach ($daily as $d) {
        $byDay[substr((string)$d['sale_day'], 0, 10)] = $d;
    }

    $days = [];
    $today = new DateTime('today', new DateTimeZone(ADMIN_TIME_ZONE));
    for ($i = ADMIN_CHART_DAYS - 1; $i >= 0; $i--) {
        $key = (clone $today)->modify("-$i day")->format('Y-m-d');
        $days[] = [
            'date' => $key,
            'total' => isset($byDay[$key]) ? (float)$byDay[$key]['total'] : 0.0,
            'orders' => isset($byDay[$key]) ? (int)$byDay[$key]['orders'] : 0,
        ];
    }

    $stores = store_rows($pdo, "
        SELECT s.id_store, s.store_name, SUM(co.total) AS total, COUNT(*) AS orders
        FROM dbo.customer_order AS co
        JOIN dbo.store AS s ON s.id_store = co.id_store
        WHERE $valid
        GROUP BY s.id_store, s.store_name
        ORDER BY total DESC");

    $categories = store_rows($pdo, "
        SELECT c.code, c.name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM dbo.order_item AS oi
        JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
        JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
        JOIN dbo.product AS p ON p.id_product = pv.id_product
        JOIN dbo.category AS c ON c.id_category = p.id_category
        WHERE oi.deleted = 0 AND $valid
        GROUP BY c.code, c.name
        ORDER BY revenue DESC");

    return [
        'sales_by_day' => $days,
        'sales_by_store' => array_map(function ($r) {
            return ['id_store' => (int)$r['id_store'], 'store' => $r['store_name'], 'total' => (float)$r['total'], 'orders' => (int)$r['orders']];
        }, $stores),
        'sales_by_category' => array_map(function ($r) {
            return ['code' => $r['code'], 'name' => $r['name'], 'units' => (int)$r['units'], 'revenue' => (float)$r['revenue']];
        }, $categories),
    ];
}

/* ---------------------------------------------------------------------
   Clientes
   --------------------------------------------------------------------- */
function admin_customers(PDO $pdo): array
{
    $rows = store_rows($pdo, "
        SELECT c.id_customer, c.first_name, c.last_name_paternal, c.email, c.phone, c.customer_since,
               t.code AS tier, t.name AS tier_name,
               DATEDIFF(DAY, c.customer_since, CAST(SYSUTCDATETIME() AS DATE)) AS days_as_customer,
               ISNULL(y.orders, 0) AS orders_12_months,
               ISNULL(y.units, 0) AS units_12_months,
               ISNULL(y.spent, 0) AS spent_12_months
        FROM dbo.customer AS c
        JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
        OUTER APPLY (
            SELECT COUNT(DISTINCT co.id_order) AS orders, SUM(oi.quantity) AS units,
                   SUM(oi.quantity * oi.unit_price) AS spent
            FROM dbo.customer_order AS co
            JOIN dbo.order_item AS oi ON oi.id_order = co.id_order AND oi.deleted = 0
            WHERE co.id_customer = c.id_customer AND co.deleted = 0
              AND co.status NOT IN ('cancelled', 'returned')
              AND co.order_date >= DATEADD(MONTH, -12, SYSUTCDATETIME())
        ) AS y
        WHERE c.deleted = 0
        ORDER BY t.id_account_tier DESC, c.customer_since, c.id_customer");

    foreach ($rows as &$r) {
        $r['id_customer'] = (int)$r['id_customer'];
        $r['days_as_customer'] = (int)$r['days_as_customer'];
        $r['orders_12_months'] = (int)$r['orders_12_months'];
        $r['units_12_months'] = (int)$r['units_12_months'];
        $r['spent_12_months'] = (float)$r['spent_12_months'];
    }
    unset($r);
    return $rows;
}

function admin_set_tier(PDO $pdo, array $admin, int $idCustomer, array $body): array
{
    $tier = (string)($body['tier'] ?? '');
    if (!in_array($tier, ['standard', 'priority'], true)) {
        throw new HttpError(400, 'El tipo de cuenta debe ser standard o priority.');
    }
    if ($idCustomer === $admin['id_customer']) {
        throw new HttpError(409, 'No puedes cambiar el tipo de tu propia cuenta.');
    }

    $stmt = $pdo->prepare("
        UPDATE c
        SET c.id_account_tier = t.id_account_tier
        FROM dbo.customer AS c
        JOIN dbo.account_tier AS current_tier ON current_tier.id_account_tier = c.id_account_tier
        CROSS JOIN dbo.account_tier AS t
        WHERE c.id_customer = :id AND c.deleted = 0
          AND current_tier.code <> 'admin'
          AND t.code = :tier");
    $stmt->bindValue(':id', $idCustomer, PDO::PARAM_INT);
    $stmt->bindValue(':tier', $tier, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El cliente no existe o es un administrador.');
    }
    return ['id_customer' => $idCustomer, 'tier' => $tier];
}

function admin_evaluate_priority(PDO $pdo): array
{
    $stmt = $pdo->query('EXEC dbo.sp_evaluar_clientes_prioritarios');
    $rows = [];
    do {
        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        }
    } while ($stmt->nextRowset());

    return ['promoted' => $rows];
}

/* ---------------------------------------------------------------------
   Pedidos
   --------------------------------------------------------------------- */
function admin_orders(PDO $pdo, ?string $status): array
{
    if ($status !== null && $status !== '' && !in_array($status, ADMIN_ORDER_STATUS, true)) {
        throw new HttpError(400, 'Estado de pedido no válido.');
    }

    $orders = store_rows($pdo, "
        SELECT TOP (100) co.id_order, co.order_date, co.status, co.channel, co.delivery_type, co.priority_shipping,
               co.subtotal, co.discount, co.shipping_cost, co.total, s.store_name,
               c.first_name, c.last_name_paternal, t.code AS tier,
               g.occasion AS gift_occasion, g.wrap_color AS gift_color, g.recipient_name AS gift_recipient,
               CASE WHEN EXISTS (
                   SELECT 1 FROM dbo.order_item AS oi
                   JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
                   JOIN dbo.product AS p ON p.id_product = pv.id_product
                   WHERE oi.id_order = co.id_order AND oi.deleted = 0 AND p.outlet_percent IS NOT NULL
               ) THEN 1 ELSE 0 END AS has_outlet
        FROM dbo.customer_order AS co
        JOIN dbo.store AS s ON s.id_store = co.id_store
        JOIN dbo.customer AS c ON c.id_customer = co.id_customer
        JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
        LEFT JOIN dbo.order_gift AS g ON g.id_order = co.id_order
        WHERE co.deleted = 0 AND (:status = '' OR co.status = :status_filter)
        ORDER BY CASE WHEN co.status IN ('pending', 'paid') THEN 0 ELSE 1 END,
                 co.priority_shipping DESC, co.order_date DESC",
        [':status' => (string)$status, ':status_filter' => (string)$status]);

    foreach ($orders as &$o) {
        $o['id_order'] = (int)$o['id_order'];
        $o['priority_shipping'] = (bool)$o['priority_shipping'];
        $o['customer'] = $o['first_name'] . ' ' . $o['last_name_paternal'];
        $o['has_outlet'] = (bool)$o['has_outlet'];
        $o['next_status'] = admin_next_status($o['status'], $o['has_outlet']);
        unset($o['first_name'], $o['last_name_paternal']);
    }
    unset($o);
    return $orders;
}

/* Los pedidos con artículos de outlet no tienen cambios ni devoluciones. */
function admin_next_status(string $status, bool $hasOutlet): array
{
    $next = ADMIN_ORDER_FLOW[$status] ?? [];
    return $hasOutlet ? array_values(array_diff($next, ['returned'])) : $next;
}

function admin_order_has_outlet(PDO $pdo, int $idOrder): bool
{
    return (bool)store_rows($pdo, '
        SELECT TOP (1) 1 AS found
        FROM dbo.order_item AS oi
        JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
        JOIN dbo.product AS p ON p.id_product = pv.id_product
        WHERE oi.id_order = :id AND oi.deleted = 0 AND p.outlet_percent IS NOT NULL', [':id' => $idOrder]);
}

function admin_set_order_status(PDO $pdo, array $admin, int $idOrder, array $body): array
{
    $status = (string)($body['status'] ?? '');
    if (!in_array($status, ADMIN_ORDER_STATUS, true)) {
        throw new HttpError(400, 'Estado de pedido no válido.');
    }

    $pdo->beginTransaction();
    try {
        $rows = store_rows($pdo, '
            SELECT status, id_store
            FROM dbo.customer_order WITH (UPDLOCK, ROWLOCK)
            WHERE id_order = :id AND deleted = 0', [':id' => $idOrder]);
        if (!$rows) {
            throw new HttpError(404, 'El pedido no existe.');
        }
        $current = $rows[0]['status'];
        if (!in_array($status, ADMIN_ORDER_FLOW[$current] ?? [], true)) {
            throw new HttpError(409, "Un pedido en estado $current no puede pasar a $status.");
        }
        $hasOutlet = admin_order_has_outlet($pdo, $idOrder);
        if ($status === 'returned' && $hasOutlet) {
            throw new HttpError(409, 'El pedido tiene artículos de outlet: no tiene cambios ni devoluciones.');
        }

        /* trg_customer_order_status_history registra quién hizo el cambio. */
        $stmt = $pdo->prepare("EXEC sys.sp_set_session_context @key = N'id_customer', @value = :id_customer");
        $stmt->bindValue(':id_customer', $admin['id_customer'], PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare('UPDATE dbo.customer_order SET status = :status WHERE id_order = :id');
        $stmt->execute([':status' => $status, ':id' => $idOrder]);

        if ($status === 'paid') {
            $stmt = $pdo->prepare("UPDATE dbo.payment SET status = 'completed' WHERE id_order = :id AND status = 'pending' AND deleted = 0");
            $stmt->execute([':id' => $idOrder]);
        }

        if ($status === 'cancelled' || $status === 'returned') {
            /* Las piezas regresan al inventario de la sucursal que surtió el pedido. */
            $stmt = $pdo->prepare('
                UPDATE i
                SET i.stock = i.stock + oi.quantity
                FROM dbo.inventory AS i
                JOIN dbo.order_item AS oi ON oi.id_variant = i.id_variant
                WHERE oi.id_order = :id AND oi.deleted = 0 AND i.id_store = :id_store AND i.deleted = 0');
            $stmt->execute([':id' => $idOrder, ':id_store' => (int)$rows[0]['id_store']]);

            $stmt = $pdo->prepare("UPDATE dbo.payment SET status = 'refunded' WHERE id_order = :id AND status = 'completed' AND deleted = 0");
            $stmt->execute([':id' => $idOrder]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    notify_safely(function () use ($pdo, $idOrder, $status) {
        notify_order_status($pdo, $idOrder, $status);
    });

    return ['id_order' => $idOrder, 'status' => $status, 'next_status' => admin_next_status($status, $hasOutlet)];
}

/* ---------------------------------------------------------------------
   Catálogo e inventario
   --------------------------------------------------------------------- */
function admin_set_price(PDO $pdo, int $idProduct, array $body): array
{
    $price = round((float)($body['base_price'] ?? 0), 2);
    if ($price < 1 || $price > 99999) {
        throw new HttpError(400, 'El precio debe estar entre $1 y $99,999.');
    }
    $stmt = $pdo->prepare('UPDATE dbo.product SET base_price = :price WHERE id_product = :id AND deleted = 0');
    $stmt->bindValue(':price', number_format($price, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':id', $idProduct, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El producto no existe.');
    }
    return ['id_product' => $idProduct, 'base_price' => $price];
}

function admin_set_stock(PDO $pdo, array $body): array
{
    $idStore = (int)($body['id_store'] ?? 0);
    $idVariant = (int)($body['id_variant'] ?? 0);
    $stock = (int)($body['stock'] ?? -1);

    $stores = array_map(function ($f) {
        return (int)$f['id_store'];
    }, array_values(DIST_FRAGMENTS));
    if (!in_array($idStore, $stores, true)) {
        throw new HttpError(400, 'La sucursal no es válida.');
    }
    if ($stock < 0 || $stock > 99999) {
        throw new HttpError(400, 'Las existencias deben estar entre 0 y 99,999.');
    }

    $stmt = $pdo->prepare('
        UPDATE dbo.inventory SET stock = :stock
        WHERE id_store = :id_store AND id_variant = :id_variant AND deleted = 0');
    $stmt->execute([':stock' => $stock, ':id_store' => $idStore, ':id_variant' => $idVariant]);

    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO dbo.inventory (id_store, id_variant, stock, min_stock, source_node)
            SELECT :id_store, pv.id_variant, :stock, 5, 'web_node'
            FROM dbo.product_variant AS pv
            WHERE pv.id_variant = :id_variant AND pv.deleted = 0");
        $stmt->execute([':id_store' => $idStore, ':stock' => $stock, ':id_variant' => $idVariant]);
        if ($stmt->rowCount() === 0) {
            throw new HttpError(404, 'La variante no existe.');
        }
    }
    return ['id_store' => $idStore, 'id_variant' => $idVariant, 'stock' => $stock];
}

/* ---------------------------------------------------------------------
   Soporte
   --------------------------------------------------------------------- */
function admin_tickets(PDO $pdo, ?string $status): array
{
    if ($status !== null && $status !== '' && !in_array($status, ['open', 'answered', 'closed'], true)) {
        throw new HttpError(400, 'Estado de ticket no válido.');
    }

    $rows = store_rows($pdo, "
        SELECT TOP (100) st.id_ticket, st.id_order, st.subject, st.message, st.priority, st.status, st.response,
               st.created_at, st.respond_by, st.answered_at,
               CASE WHEN st.status = 'open' AND st.respond_by < SYSUTCDATETIME() THEN 1 ELSE 0 END AS overdue,
               c.first_name, c.last_name_paternal, c.email, t.code AS tier
        FROM dbo.support_ticket AS st
        JOIN dbo.customer AS c ON c.id_customer = st.id_customer
        JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
        WHERE st.deleted = 0 AND (:status = '' OR st.status = :status_filter)
        ORDER BY CASE st.status WHEN 'open' THEN 0 ELSE 1 END,
                 CASE st.priority WHEN 'high' THEN 0 ELSE 1 END,
                 st.respond_by, st.id_ticket",
        [':status' => (string)$status, ':status_filter' => (string)$status]);

    foreach ($rows as &$r) {
        $r = account_ticket_row($r);
        $r['overdue'] = (bool)$r['overdue'];
        $r['customer'] = $r['first_name'] . ' ' . $r['last_name_paternal'];
        unset($r['first_name'], $r['last_name_paternal']);
    }
    unset($r);
    return $rows;
}

function admin_answer_ticket(PDO $pdo, array $admin, int $idTicket, array $body): array
{
    $status = (string)($body['status'] ?? 'answered');
    if (!in_array($status, ['answered', 'closed'], true)) {
        throw new HttpError(400, 'El estado debe ser answered o closed.');
    }
    $response = store_text($body, 'response', 2, 1000, 'La respuesta');

    $stmt = $pdo->prepare('
        UPDATE dbo.support_ticket
        SET response = :response, status = :status, id_responder = :id_responder,
            answered_at = ISNULL(answered_at, SYSUTCDATETIME())
        WHERE id_ticket = :id AND deleted = 0');
    $stmt->bindValue(':response', $response, PDO::PARAM_STR);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':id_responder', $admin['id_customer'], PDO::PARAM_INT);
    $stmt->bindValue(':id', $idTicket, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El ticket no existe.');
    }
    return ['id_ticket' => $idTicket, 'status' => $status];
}

/* ---------------------------------------------------------------------
   Comunicados
   --------------------------------------------------------------------- */
function admin_announcements(PDO $pdo): array
{
    $rows = store_rows($pdo, "
        SELECT id_announcement, audience, title, body, starts_on, ends_on,
               CASE WHEN starts_on <= CAST(SYSUTCDATETIME() AS DATE)
                     AND (ends_on IS NULL OR ends_on >= CAST(SYSUTCDATETIME() AS DATE)) THEN 1 ELSE 0 END AS active
        FROM dbo.announcement
        WHERE deleted = 0
        ORDER BY starts_on DESC, id_announcement DESC");

    foreach ($rows as &$r) {
        $r['id_announcement'] = (int)$r['id_announcement'];
        $r['active'] = (bool)$r['active'];
    }
    unset($r);
    return $rows;
}

function admin_create_announcement(PDO $pdo, array $admin, array $body): array
{
    $audience = (string)($body['audience'] ?? '');
    if (!in_array($audience, ['all', 'priority'], true)) {
        throw new HttpError(400, 'El público debe ser all o priority.');
    }
    $title = store_text($body, 'title', 3, 80, 'El título');
    $text = store_text($body, 'body', 5, 600, 'El texto');
    $startsOn = admin_date($body, 'starts_on', true);
    $endsOn = admin_date($body, 'ends_on', false);
    if ($endsOn !== null && $endsOn < $startsOn) {
        throw new HttpError(400, 'La fecha final no puede ser anterior a la inicial.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO dbo.announcement (audience, title, body, starts_on, ends_on, id_author, source_node)
        VALUES (:audience, :title, :body, :starts_on, :ends_on, :id_author, 'web_node')");
    $stmt->bindValue(':audience', $audience, PDO::PARAM_STR);
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':body', $text, PDO::PARAM_STR);
    $stmt->bindValue(':starts_on', $startsOn, PDO::PARAM_STR);
    $stmt->bindValue(':ends_on', $endsOn, $endsOn === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id_author', $admin['id_customer'], PDO::PARAM_INT);
    $stmt->execute();

    return ['id_announcement' => (int)$pdo->lastInsertId()];
}

function admin_delete_announcement(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('UPDATE dbo.announcement SET deleted = 1 WHERE id_announcement = :id AND deleted = 0');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El comunicado no existe.');
    }
    return ['id_announcement' => $id];
}

/* ---------------------------------------------------------------------
   Correos
   --------------------------------------------------------------------- */
function admin_emails(PDO $pdo): array
{
    $rows = store_rows($pdo, '
        SELECT TOP (100) id_email, id_customer, id_order, kind, to_email, subject, body, link, created_at
        FROM dbo.email_outbox
        ORDER BY created_at DESC, id_email DESC');

    foreach ($rows as &$r) {
        $r['id_email'] = (int)$r['id_email'];
        $r['id_customer'] = $r['id_customer'] !== null ? (int)$r['id_customer'] : null;
        $r['id_order'] = $r['id_order'] !== null ? (int)$r['id_order'] : null;
    }
    unset($r);
    return $rows;
}

/* ---------------------------------------------------------------------
   Enrutamiento
   --------------------------------------------------------------------- */
