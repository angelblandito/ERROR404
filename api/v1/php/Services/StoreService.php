<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use Apicore\Core\Database;
use Apicore\Core\Settings;
use DateTime;
use PDO;
use PDOException;

/**
 * Online store: catalog, reviews, customer profile and the customer's orders.
 *
 *   GET  /store/catalog                 Products with variants, stock, rating and sales
 *   GET  /store/products/{id}/reviews   Reviews of a product and its star distribution
 *   POST /store/reviews                 Publish a review (requires a session)
 *   GET  /auth/me                       Profile of the signed-in customer and its account type
 *   GET  /me/orders                     Orders of the signed-in customer, their history and gift wrap
 *   GET  /postal-codes/{cp}             State, city and neighborhoods of a postal code
 *   GET  /store/products/{id}/also-bought  Products other customers bought together with this one
 *
 * Sign up, login and logout live in Controllers/AuthController. The session
 * travels in the "Authorization: Bearer <token>" header.
 *
 * Account types (table account_tier): standard, priority and admin. The
 * benefits are read from the database and sp_crear_pedido applies them.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class StoreService
{
    /* Failed sign-ins (window of LOCK_MINUTES):
       - same email from the same IP: only that connection is stopped, the owner can still sign in from theirs;
       - any email from the same IP: stops whoever tries many accounts;
       - the whole account is locked only after many failures from several IPs (a distributed attack). */
    public const FAILS_PER_ACCOUNT_IP = 5;

    public const FAILS_PER_IP = 20;

    public const MAX_FAILED_LOGINS = 30;

    public const LOCK_MINUTES = 15;

    public const ONLINE_ID = 4;              /* Online store: it fills the home deliveries */

    public const FREE_SHIPPING_FROM = 999.00; /* Same values as dbo.sp_crear_pedido */

    public const SHIPPING_COST = 99.00;

    public const MIN_AGE = 15;

    public const GIFT_COST = 30.00;        /* Same value as dbo.sp_crear_pedido */

    public static function db(): PDO
    {
        return Database::node(Settings::instance()->JAM_DEFAULT_NODE);
    }

    public static function rows(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ---------------------------------------------------------------------
       Session. The router puts the token payload in the controller
       (TokenService::decodeToken) and the controller passes it to these methods.
       --------------------------------------------------------------------- */
    /**
     * Text field of a JSON body: '' (or $default) when it is missing, 400 when the
     * client sent a list or an object where text was expected.
     */
    public static function field(array $body, string $key, string $default = ''): string
    {
        $value = $body[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_array($value)) {
            throw new AppError("El campo $key debe ser texto.", 400);
        }
        return (string) $value;
    }

    /** Number field of a JSON body: $default when it is missing, 400 when it is not a number. */
    public static function number(array $body, string $key, int|float $default = 0): int|float
    {
        $value = $body[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return trim($value) + 0;
        }
        throw new AppError("El campo $key debe ser un número.", 400);
    }

    public static function requireCustomer(?array $actor): int
    {
        if ($actor === null) {
            throw new AppError('Inicia sesión para continuar.', 401);
        }
        return (int)$actor['id_customer'];
    }

    public static function requireAdmin(?array $actor): array
    {
        if ($actor === null) {
            throw new AppError('Inicia sesión con una cuenta de administrador.', 401);
        }
        if (empty($actor['can_manage'])) {
            throw new AppError('Esta sección es solo para administradores.', 403);
        }
        return $actor;
    }

    public static function isPriority(?array $account): bool
    {
        return $account !== null && in_array($account['tier'], ['priority', 'admin'], true);
    }

    public static function profile(PDO $pdo, int $idCustomer): array
    {
        $rows = self::rows($pdo, '
            SELECT c.id_customer, c.first_name, c.last_name_paternal, c.last_name_maternal, c.email, c.phone,
                   c.birth_date, c.sex, c.accepts_marketing, c.customer_since,
                   ll.name AS loyalty_level, ll.discount_percent AS loyalty_discount,
                   t.code AS tier, t.name AS tier_name, t.discount_percent AS tier_discount, t.free_shipping_from,
                   t.delivery_days, t.max_units_per_item, t.support_minutes, t.can_manage_store
            FROM dbo.customer AS c
            JOIN dbo.loyalty_level AS ll ON ll.id_loyalty_level = c.id_loyalty_level
            JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
            WHERE c.id_customer = :id AND c.deleted = 0', [':id' => $idCustomer]);
        if (!$rows) {
            throw new AppError('La cuenta no existe.', 404);
        }
        $row = $rows[0];
        return [
            'id_customer' => (int)$row['id_customer'],
            'first_name' => $row['first_name'],
            'last_name_paternal' => $row['last_name_paternal'],
            'last_name_maternal' => $row['last_name_maternal'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'birth_date' => $row['birth_date'],
            'sex' => $row['sex'],
            'accepts_marketing' => (bool)$row['accepts_marketing'],
            'customer_since' => $row['customer_since'],
            'loyalty_level' => $row['loyalty_level'],
            'loyalty_discount' => (float)$row['loyalty_discount'],
            'account' => [
                'tier' => $row['tier'],
                'name' => $row['tier_name'],
                'discount_percent' => (float)$row['tier_discount'],
                'free_shipping_from' => (float)$row['free_shipping_from'],
                'delivery_days' => $row['delivery_days'],
                'max_units_per_item' => (int)$row['max_units_per_item'],
                'support_minutes' => (int)$row['support_minutes'],
                'can_manage_store' => (bool)$row['can_manage_store'],
            ],
        ];
    }

    /* ---------------------------------------------------------------------
       Input validation
       --------------------------------------------------------------------- */
    public static function text(array $body, string $field, int $min, int $max, string $label): string
    {
        $value = trim(self::field($body, $field, ''));
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length < $min || $length > $max) {
            throw new AppError($min > 0
                ? "$label debe tener entre $min y $max caracteres."
                : "$label admite hasta $max caracteres.", 400);
        }
        return $value;
    }

    public static function validateRegistration(array $body): array
    {
        $data = [
            'first_name' => self::text($body, 'first_name', 2, 50, 'El nombre'),
            'last_name_paternal' => self::text($body, 'last_name_paternal', 2, 50, 'El apellido paterno'),
            'last_name_maternal' => self::text($body, 'last_name_maternal', 0, 50, 'El apellido materno'),
            'email' => strtolower(self::text($body, 'email', 5, 100, 'El correo')),
            'phone' => preg_replace('/\D/', '', self::field($body, 'phone', '')),
            'birth_date' => self::field($body, 'birth_date', ''),
            'sex' => strtoupper(self::field($body, 'sex', '')),
            'accepts_marketing' => !empty($body['accepts_marketing']) ? 1 : 0,
        ];
        $password = self::field($body, 'password', '');

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new AppError('El correo no tiene un formato válido.', 400);
        }
        if (!preg_match('/^\d{10}$/', $data['phone'])) {
            throw new AppError('El teléfono debe tener 10 dígitos.', 400);
        }
        if (!in_array($data['sex'], ['M', 'F', 'X'], true)) {
            throw new AppError('Selecciona una opción en sexo.', 400);
        }
        $birth = DateTime::createFromFormat('!Y-m-d', $data['birth_date']);
        if (!$birth || $birth->format('Y-m-d') !== $data['birth_date']) {
            throw new AppError('La fecha de nacimiento no es válida.', 400);
        }
        $age = $birth->diff(new DateTime('today'))->y;
        if ($age < self::MIN_AGE || $age > 100) {
            throw new AppError('Para crear una cuenta necesitas tener al menos ' . self::MIN_AGE . ' años.', 400);
        }
        if (empty($body['accepts_terms'])) {
            throw new AppError('Para crear la cuenta acepta el aviso de privacidad y los términos y condiciones.', 400);
        }
        if ($data['last_name_maternal'] === '') {
            $data['last_name_maternal'] = null;
        }
        $data['password_hash'] = self::passwordHash($password);
        return $data;
    }

    public static function passwordHash(string $password): string
    {
        if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new AppError('La contraseña necesita entre 8 y 72 caracteres, con letras y números.', 400);
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /* ---------------------------------------------------------------------
       Catalog
       --------------------------------------------------------------------- */
    public const PHOTO_FOLDER = __DIR__ . '/../../../img/productos';

    /* Photos available in img/productos as <id>-<color>.<extension>. */
    public static function photoFiles(): array
    {
        $files = glob(self::PHOTO_FOLDER . '/*') ?: [];
        $names = [];
        foreach ($files as $file) {
            $name = basename($file);
            /* 6-black.jpg (photo per color) or tenis-running-jam-pulse.jpg (photo of the product) */
            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\.(jpg|jpeg|png|webp)$/', $name)) {
                $names[] = $name;
            }
        }
        sort($names);
        return $names;
    }

    /* Seasons of every product (08_catalogo_temporadas.sql). When the table does not
       exist yet, or the node is a copy without it, the catalog goes on without seasons. */
    public static function productSeasonTable(PDO $pdo): bool
    {
        $exists = self::rows($pdo, "SELECT CASE WHEN OBJECT_ID(N'dbo.product_season', N'U') IS NULL THEN 0 ELSE 1 END AS ok");
        return !empty($exists[0]['ok']);
    }

    public static function productSeasons(PDO $pdo): array
    {
        if (!self::productSeasonTable($pdo)) {
            return [];
        }
        $byProduct = [];
        foreach (self::rows($pdo, '
            SELECT ps.id_product, t.code
            FROM dbo.product_season AS ps
            JOIN dbo.season_theme AS t ON t.id_theme = ps.id_theme
            WHERE ps.deleted = 0
            ORDER BY ps.id_product, t.id_theme') as $row) {
            $byProduct[(int)$row['id_product']][] = $row['code'];
        }
        return $byProduct;
    }

    public static function catalog(): array
    {
        $pdo = self::db();

        $products = self::rows($pdo, "
            SELECT p.id_product, p.name, p.description, p.id_category, c.name AS category, c.code AS category_code,
                   c.department, p.target_gender, p.age_segment, p.base_price, p.outlet_percent, p.image_url,
                   CAST(ISNULL(r.avg_rating, 0) AS DECIMAL(3,2)) AS rating,
                   ISNULL(r.reviews, 0) AS reviews,
                   ISNULL(s.sold, 0) AS sold
            FROM dbo.product AS p
            JOIN dbo.category AS c ON c.id_category = p.id_category
            LEFT JOIN (
                SELECT id_product, AVG(CAST(rating AS DECIMAL(4,2))) AS avg_rating, COUNT(*) AS reviews
                FROM dbo.product_review
                WHERE deleted = 0
                GROUP BY id_product
            ) AS r ON r.id_product = p.id_product
            LEFT JOIN (
                SELECT pv.id_product, SUM(oi.quantity) AS sold
                FROM dbo.order_item AS oi
                JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
                JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
                WHERE oi.deleted = 0 AND co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')
                GROUP BY pv.id_product
            ) AS s ON s.id_product = p.id_product
            WHERE p.deleted = 0
            ORDER BY p.id_product");

        $variants = self::rows($pdo, '
            SELECT pv.id_variant, pv.id_product, pv.sku, pv.barcode, sz.id_size, sz.code AS size, sz.name AS size_name,
                   cl.id_color, cl.code AS color_code, cl.name AS color
            FROM dbo.product_variant AS pv
            JOIN dbo.size AS sz ON sz.id_size = pv.id_size
            JOIN dbo.color AS cl ON cl.id_color = pv.id_color
            WHERE pv.deleted = 0
            ORDER BY pv.id_product, sz.id_size, cl.id_color');

        $seasons = self::productSeasons($pdo);

        $storeIds = array_values(array_map(function ($f) {
            return $f['id_store'];
        }, DistributedService::FRAGMENTS));
        $placeholders = implode(', ', array_map('intval', $storeIds));

        $stock = self::rows($pdo, "
            SELECT i.id_variant, i.id_store, i.stock
            FROM dbo.inventory AS i
            WHERE i.deleted = 0 AND i.id_store IN ($placeholders)");

        $stores = self::rows($pdo, "
            SELECT s.id_store, s.store_name, s.contact_phone, a.city, st.name AS state,
                   CASE WHEN s.id_address IS NULL THEN 1 ELSE 0 END AS is_online,
                   (SELECT config_value FROM dbo.store_config AS sc
                    WHERE sc.id_store = s.id_store AND sc.config_key = 'opening_hours' AND sc.deleted = 0) AS opening_hours
            FROM dbo.store AS s
            LEFT JOIN dbo.address AS a ON a.id_address = s.id_address
            LEFT JOIN dbo.state AS st ON st.id_state = a.id_state
            WHERE s.deleted = 0 AND s.active = 1 AND s.id_store IN ($placeholders)
            ORDER BY s.id_store");

        $stockByVariant = [];
        foreach ($stock as $row) {
            $stockByVariant[(int)$row['id_variant']][(int)$row['id_store']] = (int)$row['stock'];
        }

        $variantsByProduct = [];
        foreach ($variants as $v) {
            $id = (int)$v['id_variant'];
            $byStore = $stockByVariant[$id] ?? [];
            $variantsByProduct[(int)$v['id_product']][] = [
                'id_variant' => $id,
                'sku' => $v['sku'],
                'barcode' => $v['barcode'],
                'id_size' => (int)$v['id_size'],
                'size' => $v['size'],
                'size_name' => $v['size_name'],
                'id_color' => (int)$v['id_color'],
                'color_code' => $v['color_code'],
                'color' => $v['color'],
                'stock' => (object)$byStore,
                'stock_total' => array_sum($byStore),
            ];
        }

        foreach ($products as &$p) {
            $p['id_product'] = (int)$p['id_product'];
            $p['id_category'] = (int)$p['id_category'];
            $p['base_price'] = (float)$p['base_price'];
            /* Same calculation as dbo.sp_crear_pedido for the outlet price. */
            $p['outlet_percent'] = $p['outlet_percent'] !== null ? (int)$p['outlet_percent'] : null;
            $p['sale_price'] = $p['outlet_percent'] !== null
                ? round($p['base_price'] * (100 - $p['outlet_percent']) / 100, 2)
                : $p['base_price'];
            $p['rating'] = (float)$p['rating'];
            $p['reviews'] = (int)$p['reviews'];
            $p['sold'] = (int)$p['sold'];
            $p['seasons'] = $seasons[$p['id_product']] ?? [];
            $p['variants'] = $variantsByProduct[$p['id_product']] ?? [];
            $p['stock_total'] = array_sum(array_column($p['variants'], 'stock_total'));
        }
        unset($p);

        foreach ($stores as &$s) {
            $s['id_store'] = (int)$s['id_store'];
            $s['is_online'] = (bool)$s['is_online'];
        }
        unset($s);

        $tiers = array_map(function ($t) {
            return [
                'tier' => $t['code'],
                'name' => $t['name'],
                'discount_percent' => (float)$t['discount_percent'],
                'free_shipping_from' => (float)$t['free_shipping_from'],
                'delivery_days' => $t['delivery_days'],
                'max_units_per_item' => (int)$t['max_units_per_item'],
                'support_minutes' => (int)$t['support_minutes'],
                'can_manage_store' => (bool)$t['can_manage_store'],
            ];
        }, self::rows($pdo, '
            SELECT code, name, discount_percent, free_shipping_from, delivery_days, max_units_per_item,
                   support_minutes, can_manage_store
            FROM dbo.account_tier
            ORDER BY id_account_tier'));

        return [
            'products' => $products,
            'stores' => $stores,
            'online_store' => self::ONLINE_ID,
            'shipping' => ['free_from' => self::FREE_SHIPPING_FROM, 'cost' => self::SHIPPING_COST],
            'gift_wrap' => ['cost' => self::GIFT_COST],
            'photos' => self::photoFiles(),
            'account_tiers' => $tiers,
        ];
    }

    /* ---------------------------------------------------------------------
       Reviews
       --------------------------------------------------------------------- */
    public static function reviews(int $idProduct): array
    {
        $reviews = self::rows(self::db(), '
            SELECT TOP (50) r.id_review, r.rating, r.title, r.comment, r.verified_purchase, r.created_at,
                   c.first_name, LEFT(c.last_name_paternal, 1) AS last_initial
            FROM dbo.product_review AS r
            JOIN dbo.customer AS c ON c.id_customer = r.id_customer
            WHERE r.id_product = :id AND r.deleted = 0
            ORDER BY r.created_at DESC, r.id_review DESC', [':id' => $idProduct]);

        $histogram = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as &$r) {
            $r['id_review'] = (int)$r['id_review'];
            $r['rating'] = (int)$r['rating'];
            $r['verified_purchase'] = (bool)$r['verified_purchase'];
            $r['author'] = $r['first_name'] . ' ' . $r['last_initial'] . '.';
            unset($r['first_name'], $r['last_initial']);
            $histogram[$r['rating']]++;
        }
        unset($r);

        return ['reviews' => $reviews, 'histogram' => $histogram];
    }

    public static function createReview(?array $actor, array $body): array
    {
        $pdo = self::db();
        $idCustomer = self::requireCustomer($actor);

        $idProduct = (int) self::number($body, 'id_product', 0);
        $rating = (int) self::number($body, 'rating', 0);
        if ($rating < 1 || $rating > 5) {
            throw new AppError('La calificación debe ser de 1 a 5 estrellas.', 400);
        }
        $title = self::text($body, 'title', 3, 80, 'El título');
        $comment = self::text($body, 'comment', 0, 1000, 'El comentario');

        $stmt = $pdo->prepare("
            INSERT INTO dbo.product_review (id_product, id_customer, rating, title, comment, verified_purchase, source_node)
            SELECT p.id_product, :id_customer, :rating, :title, NULLIF(:comment, ''),
                   CASE WHEN EXISTS (
                       SELECT 1
                       FROM dbo.order_item AS oi
                       JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
                       JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
                       WHERE co.id_customer = :id_customer_check AND pv.id_product = p.id_product
                         AND co.status NOT IN ('cancelled', 'returned')) THEN 1 ELSE 0 END,
                   'web_node'
            FROM dbo.product AS p
            WHERE p.id_product = :id_product AND p.deleted = 0");
        $stmt->bindValue(':id_customer', $idCustomer, PDO::PARAM_INT);
        $stmt->bindValue(':id_customer_check', $idCustomer, PDO::PARAM_INT);
        $stmt->bindValue(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
        $stmt->bindValue(':id_product', $idProduct, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $ex) {
            if ((int)($ex->errorInfo[1] ?? 0) === 2601) {
                throw new AppError('Ya publicaste una reseña de este producto.', 409);
            }
            throw $ex;
        }
        if ($stmt->rowCount() === 0) {
            throw new AppError('El producto no existe.', 404);
        }
        return ['id_review' => (int)$pdo->lastInsertId()];
    }

    public static function myOrders(?array $actor): array
    {
        $pdo = self::db();
        $idCustomer = self::requireCustomer($actor);

        $orders = self::rows($pdo, "
            SELECT co.id_order, co.order_date, co.status, co.channel, co.delivery_type, co.subtotal, co.discount,
                   co.shipping_cost, co.gift_cost, co.total, co.priority_shipping, s.store_name, pr.code AS promotion_code,
                   g.occasion AS gift_occasion, g.wrap_color AS gift_color, g.recipient_name AS gift_recipient, g.message AS gift_message,
                   pay.method AS payment_method, pay.status AS payment_status,
                   a.street, a.ext_number, a.neighborhood, a.city, a.zip_code
            FROM dbo.customer_order AS co
            JOIN dbo.store AS s ON s.id_store = co.id_store
            LEFT JOIN dbo.promotion AS pr ON pr.id_promotion = co.id_promotion
            LEFT JOIN dbo.address AS a ON a.id_address = co.id_address
            LEFT JOIN dbo.order_gift AS g ON g.id_order = co.id_order
            OUTER APPLY (
                SELECT TOP (1) p.method, p.status
                FROM dbo.payment AS p
                WHERE p.id_order = co.id_order AND p.deleted = 0
                ORDER BY p.id_payment DESC
            ) AS pay
            WHERE co.id_customer = :id AND co.deleted = 0
            ORDER BY co.order_date DESC", [':id' => $idCustomer]);

        if (!$orders) {
            return [];
        }

        $ids = implode(', ', array_map(function ($o) {
            return (int)$o['id_order'];
        }, $orders));
        $items = self::rows($pdo, "
            SELECT oi.id_order, oi.id_variant, oi.quantity, oi.unit_price, p.id_product, p.name, sz.code AS size,
                   cl.name AS color, cl.code AS color_code, c.code AS category_code,
                   CASE WHEN p.outlet_percent IS NULL THEN 0 ELSE 1 END AS outlet
            FROM dbo.order_item AS oi
            JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
            JOIN dbo.product AS p ON p.id_product = pv.id_product
            JOIN dbo.category AS c ON c.id_category = p.id_category
            JOIN dbo.size AS sz ON sz.id_size = pv.id_size
            JOIN dbo.color AS cl ON cl.id_color = pv.id_color
            WHERE oi.deleted = 0 AND oi.id_order IN ($ids)");

        $history = self::rows($pdo, "
            SELECT id_order, from_status, to_status, changed_at
            FROM dbo.order_status_history
            WHERE id_order IN ($ids)
            ORDER BY changed_at, id_history");

        $byOrder = [];
        foreach ($items as $it) {
            $byOrder[(int)$it['id_order']][] = $it;
        }
        $historyByOrder = [];
        foreach ($history as $h) {
            $historyByOrder[(int)$h['id_order']][] = [
                'from_status' => $h['from_status'],
                'status' => $h['to_status'],
                'changed_at' => $h['changed_at'],
            ];
        }
        foreach ($orders as &$o) {
            $o['id_order'] = (int)$o['id_order'];
            $o['priority_shipping'] = (bool)$o['priority_shipping'];
            $o['items'] = $byOrder[$o['id_order']] ?? [];
            $o['history'] = $historyByOrder[$o['id_order']] ?? [];
        }
        unset($o);
        return $orders;
    }

    /* ---------------------------------------------------------------------
       Postal codes
       --------------------------------------------------------------------- */
    /* Coupon in effect by code. Same rule as dbo.sp_crear_pedido: not deleted and today's date (UTC)
       between start_date and end_date. The procedure calculates the final discount again. */
    public static function coupon(string $code): array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9_-]{3,20}$/', $code)) {
            throw new AppError('El cupón no existe o no está vigente.', 404);
        }
        $rows = self::rows(self::db(), '
            SELECT code, description, discount_percent, end_date
            FROM dbo.promotion
            WHERE code = :code AND deleted = 0
              AND CAST(SYSUTCDATETIME() AS DATE) BETWEEN start_date AND end_date', [':code' => $code]);
        if (!$rows) {
            throw new AppError('El cupón no existe o no está vigente.', 404);
        }
        $row = $rows[0];
        $row['discount_percent'] = (float)$row['discount_percent'];
        return $row;
    }

    public static function postalCode(string $zip): array
    {
        $rows = self::rows(self::db(), '
            SELECT pc.zip_code, pc.settlement, pc.settlement_type, pc.municipality, pc.city, pc.id_state, s.name AS state
            FROM dbo.postal_code AS pc
            JOIN dbo.state AS s ON s.id_state = pc.id_state
            WHERE pc.zip_code = :zip
            ORDER BY pc.settlement', [':zip' => $zip]);
        if (!$rows) {
            throw new AppError('No tenemos registrado ese código postal. Escribe la colonia, la ciudad y el estado.', 404);
        }
        return [
            'zip_code' => $zip,
            'id_state' => (int)$rows[0]['id_state'],
            'state' => $rows[0]['state'],
            'city' => $rows[0]['city'],
            'municipality' => $rows[0]['municipality'],
            'settlements' => array_map(function ($r) {
                return ['name' => $r['settlement'], 'type' => $r['settlement_type']];
            }, $rows),
        ];
    }

    /* ---------------------------------------------------------------------
       Recomendaciones
       --------------------------------------------------------------------- */
    /* Products that appear in the same orders as the given product.
       When there are few, the best sellers of the same department fill the list. */
    public static function alsoBought(int $idProduct): array
    {
        $pdo = self::db();
        $rows = self::rows($pdo, "
            SELECT TOP (6) other.id_product, COUNT(DISTINCT oi.id_order) AS orders
            FROM dbo.order_item AS oi
            JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
            JOIN dbo.customer_order AS co ON co.id_order = oi.id_order
            JOIN dbo.order_item AS oi2 ON oi2.id_order = oi.id_order AND oi2.deleted = 0
            JOIN dbo.product_variant AS other ON other.id_variant = oi2.id_variant
            JOIN dbo.product AS p ON p.id_product = other.id_product AND p.deleted = 0
            WHERE pv.id_product = :id AND other.id_product <> :id_self
              AND oi.deleted = 0 AND co.deleted = 0 AND co.status NOT IN ('cancelled', 'returned')
            GROUP BY other.id_product
            ORDER BY orders DESC, other.id_product", [':id' => $idProduct, ':id_self' => $idProduct]);

        $ids = array_map(function ($r) {
            return (int)$r['id_product'];
        }, $rows);
        $source = $ids ? 'orders' : 'department';

        if (count($ids) < 4) {
            $exclude = implode(', ', array_merge([$idProduct], $ids));
            $fill = self::rows($pdo, "
                SELECT TOP (" . (4 - count($ids)) . ") p.id_product
                FROM dbo.product AS p
                JOIN dbo.category AS c ON c.id_category = p.id_category
                LEFT JOIN (
                    SELECT pv.id_product, SUM(oi.quantity) AS sold
                    FROM dbo.order_item AS oi
                    JOIN dbo.product_variant AS pv ON pv.id_variant = oi.id_variant
                    WHERE oi.deleted = 0
                    GROUP BY pv.id_product
                ) AS s ON s.id_product = p.id_product
                WHERE p.deleted = 0 AND p.outlet_percent IS NULL AND p.id_product NOT IN ($exclude)
                  AND c.department = (SELECT c2.department FROM dbo.product AS p2
                                      JOIN dbo.category AS c2 ON c2.id_category = p2.id_category
                                      WHERE p2.id_product = :id)
                ORDER BY ISNULL(s.sold, 0) DESC, p.id_product", [':id' => $idProduct]);
            foreach ($fill as $f) {
                $ids[] = (int)$f['id_product'];
            }
        }

        return ['source' => $source, 'products' => $ids];
    }
}
