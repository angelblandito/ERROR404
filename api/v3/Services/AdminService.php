<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use DateTime;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Store administration (only accounts with account_tier.can_manage_store = 1).
 *
 *   GET    /admin/summary                  Indicators, sales per day (30 days), per branch and per category
 *   GET    /admin/customers                Customers with account type, seniority and purchases of the year
 *   PUT    /admin/customers/{id}/tier      {tier: 'standard' | 'priority'}
 *   POST   /admin/customers/evaluate       Runs dbo.sp_evaluar_clientes_prioritarios
 *   GET    /admin/orders?status=           Orders of the central node, priority accounts first
 *   PUT    /admin/orders/{id}/status       {status}; cancelling or returning puts the stock back
 *   PUT    /admin/products/{id}            {base_price}
 *   PUT    /admin/inventory                {id_store, id_variant, stock}
 *   GET    /admin/tickets?status=          Support queue sorted by priority and deadline
 *   PUT    /admin/tickets/{id}             {response, status: 'answered' | 'closed'}
 *   GET    /admin/announcements            Every announcement
 *   POST   /admin/announcements            {audience, title, body, starts_on, ends_on?}
 *   DELETE /admin/announcements/{id}       Logical delete
 *   GET    /admin/emails                   Simulated email outbox
 *   GET    /admin/market                   Competitor prices (see MarketService)
 *   POST   /admin/market
 *   PUT    /admin/market/{id}
 *   DELETE /admin/market/{id}
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class AdminService
{
    public const TIME_ZONE = 'America/Mexico_City';

    public const SQL_TIME_ZONE = 'Central Standard Time (Mexico)';

    public const CHART_DAYS = 30;

    public const ORDER_STATUS = ['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'returned'];

    /* Status changes allowed for an order. */
    public const ORDER_FLOW = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['shipped', 'delivered', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public static function date(array $body, string $field, bool $required): ?string
    {
        $value = trim(StoreService::field($body, $field, ''));
        if ($value === '' && !$required) {
            return null;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new AppError('La fecha debe tener el formato AAAA-MM-DD.', 400);
        }
        return $value;
    }

    /* ---------------------------------------------------------------------
       Resumen
       --------------------------------------------------------------------- */
    public static function summary(PDO $pdo): array
    {
        $sales = StoreService::rows($pdo, "
            SELECT
                ISNULL(SUM(CASE WHEN co.order_date >= CAST(SYSUTCDATETIME() AS DATE) THEN co.total END), 0) AS sales_today,
                ISNULL(SUM(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) THEN co.total END), 0) AS sales_30_days,
                COUNT(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) THEN 1 END) AS orders_30_days,
                ISNULL(SUM(CASE WHEN co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME()) AND co.priority_shipping = 1
                                THEN co.total END), 0) AS priority_sales_30_days
            FROM dbo.customer_order AS co
            WHERE co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')")[0];

        $pending = StoreService::rows($pdo, "
            SELECT COUNT(*) AS pending_orders,
                   COUNT(CASE WHEN priority_shipping = 1 THEN 1 END) AS pending_priority
            FROM dbo.customer_order
            WHERE deleted = 0 AND status IN ('pending', 'paid')")[0];

        $tickets = StoreService::rows($pdo, "
            SELECT COUNT(*) AS open_tickets,
                   COUNT(CASE WHEN priority = 'high' THEN 1 END) AS open_high,
                   COUNT(CASE WHEN respond_by < SYSUTCDATETIME() THEN 1 END) AS overdue
            FROM dbo.support_ticket
            WHERE deleted = 0 AND status = 'open'")[0];

        $tiers = StoreService::rows($pdo, '
            SELECT t.code, t.name, COUNT(c.id_customer) AS customers
            FROM dbo.account_tier AS t
            LEFT JOIN dbo.customer AS c ON c.id_account_tier = t.id_account_tier AND c.deleted = 0
            GROUP BY t.id_account_tier, t.code, t.name
            ORDER BY t.id_account_tier');

        $top = StoreService::rows($pdo, "
            SELECT TOP (5) p.id_product, p.name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
            FROM dbo.order_item AS oi
            JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
            JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
            JOIN dbo.product AS p ON p.id_product = pv.id_product
            WHERE oi.deleted = 0 AND co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')
              AND co.order_date >= DATEADD(DAY, -30, SYSUTCDATETIME())
            GROUP BY p.id_product, p.name
            ORDER BY units DESC, revenue DESC");

        $lowStock = StoreService::rows($pdo, '
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
        ], self::salesCharts($pdo));
    }

    /* Sales of the last 30 days grouped per day (central Mexico time), per branch
       and per category. Days without sales appear with 0. */
    public static function salesCharts(PDO $pdo): array
    {
        $localDate = "CAST(co.order_date AT TIME ZONE 'UTC' AT TIME ZONE '" . self::SQL_TIME_ZONE . "' AS DATE)";
        $since = "DATEADD(DAY, -" . (self::CHART_DAYS - 1) . ", CAST(SYSUTCDATETIME() AT TIME ZONE 'UTC' AT TIME ZONE '" . self::SQL_TIME_ZONE . "' AS DATE))";
        $valid = "co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned') AND $localDate >= $since";

        $daily = StoreService::rows($pdo, "
            SELECT $localDate AS sale_day, SUM(co.total) AS total, COUNT(*) AS orders
            FROM dbo.customer_order AS co
            WHERE $valid
            GROUP BY $localDate");
        $byDay = [];
        foreach ($daily as $d) {
            $byDay[substr((string)$d['sale_day'], 0, 10)] = $d;
        }

        $days = [];
        $today = new DateTime('today', new DateTimeZone(self::TIME_ZONE));
        for ($i = self::CHART_DAYS - 1; $i >= 0; $i--) {
            $key = (clone $today)->modify("-$i day")->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'total' => isset($byDay[$key]) ? (float)$byDay[$key]['total'] : 0.0,
                'orders' => isset($byDay[$key]) ? (int)$byDay[$key]['orders'] : 0,
            ];
        }

        $stores = StoreService::rows($pdo, "
            SELECT s.id_store, s.store_name, SUM(co.total) AS total, COUNT(*) AS orders
            FROM dbo.customer_order AS co
            JOIN dbo.store AS s ON s.id_store = co.id_store
            WHERE $valid
            GROUP BY s.id_store, s.store_name
            ORDER BY total DESC");

        $categories = StoreService::rows($pdo, "
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
    public static function customers(PDO $pdo): array
    {
        $rows = StoreService::rows($pdo, "
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

    public static function setTier(PDO $pdo, array $admin, int $idCustomer, array $body): array
    {
        $tier = StoreService::field($body, 'tier', '');
        if (!in_array($tier, ['standard', 'priority'], true)) {
            throw new AppError('El tipo de cuenta debe ser standard o priority.', 400);
        }
        if ($idCustomer === $admin['id_customer']) {
            throw new AppError('No puedes cambiar el tipo de tu propia cuenta.', 409);
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
            throw new AppError('El cliente no existe o es un administrador.', 404);
        }
        return ['id_customer' => $idCustomer, 'tier' => $tier];
    }

    public static function evaluatePriority(PDO $pdo): array
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
    public static function orders(PDO $pdo, ?string $status): array
    {
        if ($status !== null && $status !== '' && !in_array($status, self::ORDER_STATUS, true)) {
            throw new AppError('Estado de pedido no válido.', 400);
        }

        $orders = StoreService::rows($pdo, "
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
            $o['next_status'] = self::nextStatus($o['status'], $o['has_outlet']);
            unset($o['first_name'], $o['last_name_paternal']);
        }
        unset($o);
        return $orders;
    }

    /* Orders with outlet items have no exchanges or returns. */
    public static function nextStatus(string $status, bool $hasOutlet): array
    {
        $next = self::ORDER_FLOW[$status] ?? [];
        return $hasOutlet ? array_values(array_diff($next, ['returned'])) : $next;
    }

    public static function orderHasOutlet(PDO $pdo, int $idOrder): bool
    {
        return (bool)StoreService::rows($pdo, '
            SELECT TOP (1) 1 AS found
            FROM dbo.order_item AS oi
            JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
            JOIN dbo.product AS p ON p.id_product = pv.id_product
            WHERE oi.id_order = :id AND oi.deleted = 0 AND p.outlet_percent IS NOT NULL', [':id' => $idOrder]);
    }

    public static function setOrderStatus(PDO $pdo, array $admin, int $idOrder, array $body): array
    {
        $status = StoreService::field($body, 'status', '');
        if (!in_array($status, self::ORDER_STATUS, true)) {
            throw new AppError('Estado de pedido no válido.', 400);
        }

        $pdo->beginTransaction();
        try {
            $rows = StoreService::rows($pdo, '
                SELECT status, id_store
                FROM dbo.customer_order WITH (UPDLOCK, ROWLOCK)
                WHERE id_order = :id AND deleted = 0', [':id' => $idOrder]);
            if (!$rows) {
                throw new AppError('El pedido no existe.', 404);
            }
            $current = $rows[0]['status'];
            if (!in_array($status, self::ORDER_FLOW[$current] ?? [], true)) {
                throw new AppError("Un pedido en estado $current no puede pasar a $status.", 409);
            }
            $hasOutlet = self::orderHasOutlet($pdo, $idOrder);
            if ($status === 'returned' && $hasOutlet) {
                throw new AppError('El pedido tiene artículos de outlet: no tiene cambios ni devoluciones.', 409);
            }

            /* trg_customer_order_status_history records who made the change. */
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
                /* The pieces go back to the inventory of the branch that filled the order. */
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

        NotificationService::safely(function () use ($pdo, $idOrder, $status) {
            NotificationService::orderStatus($pdo, $idOrder, $status);
        });

        return ['id_order' => $idOrder, 'status' => $status, 'next_status' => self::nextStatus($status, $hasOutlet)];
    }

    /* ---------------------------------------------------------------------
       Catalog and inventory
       --------------------------------------------------------------------- */
    public static function setPrice(PDO $pdo, int $idProduct, array $body): array
    {
        $price = round((float) StoreService::number($body, 'base_price', 0), 2);
        if ($price < 1 || $price > 99999) {
            throw new AppError('El precio debe estar entre $1 y $99,999.', 400);
        }
        $stmt = $pdo->prepare('UPDATE dbo.product SET base_price = :price WHERE id_product = :id AND deleted = 0');
        $stmt->bindValue(':price', number_format($price, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':id', $idProduct, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            throw new AppError('El producto no existe.', 404);
        }
        return ['id_product' => $idProduct, 'base_price' => $price];
    }

    public static function setStock(PDO $pdo, array $body): array
    {
        $idStore = (int) StoreService::number($body, 'id_store', 0);
        $idVariant = (int) StoreService::number($body, 'id_variant', 0);
        $stock = (int) StoreService::number($body, 'stock', -1);

        $stores = array_map(function ($f) {
            return (int)$f['id_store'];
        }, array_values(DistributedService::FRAGMENTS));
        if (!in_array($idStore, $stores, true)) {
            throw new AppError('La sucursal no es válida.', 400);
        }
        if ($stock < 0 || $stock > 99999) {
            throw new AppError('Las existencias deben estar entre 0 y 99,999.', 400);
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
                throw new AppError('La variante no existe.', 404);
            }
        }
        return ['id_store' => $idStore, 'id_variant' => $idVariant, 'stock' => $stock];
    }

    /* ---------------------------------------------------------------------
       Soporte
       --------------------------------------------------------------------- */
    public static function tickets(PDO $pdo, ?string $status): array
    {
        if ($status !== null && $status !== '' && !in_array($status, ['open', 'answered', 'closed'], true)) {
            throw new AppError('Estado de ticket no válido.', 400);
        }

        $rows = StoreService::rows($pdo, "
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
            $r = AccountService::ticketRow($r);
            $r['overdue'] = (bool)$r['overdue'];
            $r['customer'] = $r['first_name'] . ' ' . $r['last_name_paternal'];
            unset($r['first_name'], $r['last_name_paternal']);
        }
        unset($r);
        return $rows;
    }

    public static function answerTicket(PDO $pdo, array $admin, int $idTicket, array $body): array
    {
        $status = StoreService::field($body, 'status', 'answered');
        if (!in_array($status, ['answered', 'closed'], true)) {
            throw new AppError('El estado debe ser answered o closed.', 400);
        }
        $response = StoreService::text($body, 'response', 2, 1000, 'La respuesta');

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
            throw new AppError('El ticket no existe.', 404);
        }
        return ['id_ticket' => $idTicket, 'status' => $status];
    }

    /* ---------------------------------------------------------------------
       Comunicados
       --------------------------------------------------------------------- */
    public static function announcements(PDO $pdo): array
    {
        $rows = StoreService::rows($pdo, "
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

    public static function createAnnouncement(PDO $pdo, array $admin, array $body): array
    {
        $audience = StoreService::field($body, 'audience', '');
        if (!in_array($audience, ['all', 'priority'], true)) {
            throw new AppError('El público debe ser all o priority.', 400);
        }
        $title = StoreService::text($body, 'title', 3, 80, 'El título');
        $text = StoreService::text($body, 'body', 5, 600, 'El texto');
        $startsOn = self::date($body, 'starts_on', true);
        $endsOn = self::date($body, 'ends_on', false);
        if ($endsOn !== null && $endsOn < $startsOn) {
            throw new AppError('La fecha final no puede ser anterior a la inicial.', 400);
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

    public static function deleteAnnouncement(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('UPDATE dbo.announcement SET deleted = 1 WHERE id_announcement = :id AND deleted = 0');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new AppError('El comunicado no existe.', 404);
        }
        return ['id_announcement' => $id];
    }

    /* ---------------------------------------------------------------------
       Correos
       --------------------------------------------------------------------- */
    public static function emails(PDO $pdo): array
    {
        $rows = StoreService::rows($pdo, '
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
}
