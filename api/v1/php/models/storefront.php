<?php
/*
 * Tienda en línea: catálogo, cuentas de cliente, reseñas y pedidos del cliente.
 *
 *   GET  /store/catalog                 Productos con variantes, existencias, calificación y ventas
 *   GET  /store/products/{id}/reviews   Reseñas de un producto y su distribución de estrellas
 *   POST /store/reviews                 Publicar una reseña (requiere sesión)
 *   POST /auth/register                 Crear cuenta de cliente (devuelve token de sesión)
 *   POST /auth/login                    Iniciar sesión (devuelve token de sesión)
 *   POST /auth/logout                   Cerrar la sesión actual
 *   GET  /auth/me                       Datos del cliente con sesión y su tipo de cuenta
 *   GET  /me/orders                     Pedidos del cliente con sesión, su historial y el empaque de regalo
 *   GET  /postal-codes/{cp}             Estado, ciudad y colonias de un código postal
 *   GET  /store/products/{id}/also-bought  Productos que otros clientes compraron junto con este
 *
 * La sesión viaja en el encabezado "Authorization: Bearer <token>". En la base
 * solo se guarda el SHA-256 del token.
 *
 * Tipos de cuenta (tabla account_tier): standard, priority y admin. Los
 * beneficios se leen de la base y sp_crear_pedido los aplica al cobrar.
 */

const STORE_SESSION_DAYS = 7;
const STORE_MAX_FAILED_LOGINS = 5;
const STORE_LOCK_MINUTES = 15;
const STORE_ONLINE_ID = 4;              /* Tienda en línea: surte los envíos a domicilio */
const STORE_FREE_SHIPPING_FROM = 999.00; /* Mismos valores que dbo.sp_crear_pedido */
const STORE_SHIPPING_COST = 99.00;
const STORE_MIN_AGE = 15;
const STORE_GIFT_COST = 30.00;        /* Mismo valor que dbo.sp_crear_pedido */

class StoreHttpException extends RuntimeException
{
    public $status;

    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function store_ok(int $status, $data, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

/* Ejecuta un endpoint y convierte cualquier error en la respuesta JSON correspondiente. */
function store_run(string $uri, callable $action): void
{
    try {
        $action();
    } catch (StoreHttpException $e) {
        api_send_error(api_error($e->status, $e->getMessage()));
    } catch (PDOException $e) {
        api_send_error(api_db_error($e));
    } catch (Throwable $e) {
        error_log('Error en ' . $uri . ': ' . $e->getMessage());
        api_send_error(api_error(500, 'Error interno de la tienda.'));
    }
}

function store_db(): PDO
{
    return jam_node(JAM_DEFAULT_NODE);
}

function store_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------------------------------------------------------------
   Sesión
   --------------------------------------------------------------------- */
function store_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
            }
        }
    }
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $m) ? strtolower($m[1]) : null;
}

/* Cliente de la sesión y su tipo de cuenta, o null si no hay sesión válida. */
function store_current_account(PDO $pdo): ?array
{
    $token = store_bearer_token();
    if ($token === null) {
        return null;
    }
    $rows = store_rows($pdo, '
        SELECT s.id_customer, t.code AS tier, t.can_manage_store, t.support_minutes
        FROM dbo.customer_session AS s
        JOIN dbo.customer AS c ON c.id_customer = s.id_customer AND c.deleted = 0
        JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
        WHERE s.token_hash = :token_hash
          AND s.revoked = 0
          AND s.expires_at > SYSUTCDATETIME()', [':token_hash' => hash('sha256', $token)]);
    if (!$rows) {
        return null;
    }
    return [
        'id_customer' => (int)$rows[0]['id_customer'],
        'tier' => $rows[0]['tier'],
        'can_manage' => (bool)$rows[0]['can_manage_store'],
        'support_minutes' => (int)$rows[0]['support_minutes'],
    ];
}

function store_current_customer(PDO $pdo): ?int
{
    $account = store_current_account($pdo);
    return $account !== null ? $account['id_customer'] : null;
}

function store_require_customer(PDO $pdo): int
{
    $id = store_current_customer($pdo);
    if ($id === null) {
        throw new StoreHttpException(401, 'Inicia sesión para continuar.');
    }
    return $id;
}

function store_require_admin(PDO $pdo): array
{
    $account = store_current_account($pdo);
    if ($account === null) {
        throw new StoreHttpException(401, 'Inicia sesión con una cuenta de administrador.');
    }
    if (!$account['can_manage']) {
        throw new StoreHttpException(403, 'Esta sección es solo para administradores.');
    }
    return $account;
}

function store_is_priority(?array $account): bool
{
    return $account !== null && in_array($account['tier'], ['priority', 'admin'], true);
}

function store_create_session(PDO $pdo, int $idCustomer): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('
        INSERT INTO dbo.customer_session (token_hash, id_customer, expires_at)
        VALUES (:token_hash, :id_customer, DATEADD(DAY, :days, SYSUTCDATETIME()))');
    $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
    $stmt->bindValue(':id_customer', $idCustomer, PDO::PARAM_INT);
    $stmt->bindValue(':days', STORE_SESSION_DAYS, PDO::PARAM_INT);
    $stmt->execute();
    return $token;
}

function store_profile(PDO $pdo, int $idCustomer): array
{
    $rows = store_rows($pdo, '
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
        throw new StoreHttpException(404, 'La cuenta no existe.');
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
   Validación de datos de entrada
   --------------------------------------------------------------------- */
function store_text(array $body, string $field, int $min, int $max, string $label): string
{
    $value = trim((string)($body[$field] ?? ''));
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length < $min || $length > $max) {
        throw new StoreHttpException(400, $min > 0
            ? "$label debe tener entre $min y $max caracteres."
            : "$label admite hasta $max caracteres.");
    }
    return $value;
}

function store_validate_registration(array $body): array
{
    $data = [
        'first_name' => store_text($body, 'first_name', 2, 50, 'El nombre'),
        'last_name_paternal' => store_text($body, 'last_name_paternal', 2, 50, 'El apellido paterno'),
        'last_name_maternal' => store_text($body, 'last_name_maternal', 0, 50, 'El apellido materno'),
        'email' => strtolower(store_text($body, 'email', 5, 100, 'El correo')),
        'phone' => preg_replace('/\D/', '', (string)($body['phone'] ?? '')),
        'birth_date' => (string)($body['birth_date'] ?? ''),
        'sex' => strtoupper((string)($body['sex'] ?? '')),
        'accepts_marketing' => !empty($body['accepts_marketing']) ? 1 : 0,
    ];
    $password = (string)($body['password'] ?? '');

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new StoreHttpException(400, 'El correo no tiene un formato válido.');
    }
    if (!preg_match('/^\d{10}$/', $data['phone'])) {
        throw new StoreHttpException(400, 'El teléfono debe tener 10 dígitos.');
    }
    if (!in_array($data['sex'], ['M', 'F', 'X'], true)) {
        throw new StoreHttpException(400, 'Selecciona una opción en sexo.');
    }
    $birth = DateTime::createFromFormat('!Y-m-d', $data['birth_date']);
    if (!$birth || $birth->format('Y-m-d') !== $data['birth_date']) {
        throw new StoreHttpException(400, 'La fecha de nacimiento no es válida.');
    }
    $age = $birth->diff(new DateTime('today'))->y;
    if ($age < STORE_MIN_AGE || $age > 100) {
        throw new StoreHttpException(400, 'Para crear una cuenta necesitas tener al menos ' . STORE_MIN_AGE . ' años.');
    }
    if (empty($body['accepts_terms'])) {
        throw new StoreHttpException(400, 'Para crear la cuenta acepta el aviso de privacidad y los términos y condiciones.');
    }
    if ($data['last_name_maternal'] === '') {
        $data['last_name_maternal'] = null;
    }
    $data['password_hash'] = store_password_hash($password);
    return $data;
}

function store_password_hash(string $password): string
{
    if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new StoreHttpException(400, 'La contraseña necesita entre 8 y 72 caracteres, con letras y números.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/* ---------------------------------------------------------------------
   Catálogo
   --------------------------------------------------------------------- */
const STORE_PHOTO_FOLDER = __DIR__ . '/../../../../img/productos';

/* Fotos disponibles en img/productos con el formato <id>-<color>.<extensión>. */
function store_photo_files(): array
{
    $files = glob(STORE_PHOTO_FOLDER . '/*') ?: [];
    $names = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (preg_match('/^\d+-[a-z]+\.(jpg|jpeg|png|webp)$/', $name)) {
            $names[] = $name;
        }
    }
    sort($names);
    return $names;
}

function store_catalog(): array
{
    $pdo = store_db();

    $products = store_rows($pdo, "
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

    $variants = store_rows($pdo, '
        SELECT pv.id_variant, pv.id_product, pv.sku, pv.barcode, sz.id_size, sz.code AS size, sz.name AS size_name,
               cl.id_color, cl.code AS color_code, cl.name AS color
        FROM dbo.product_variant AS pv
        JOIN dbo.size AS sz ON sz.id_size = pv.id_size
        JOIN dbo.color AS cl ON cl.id_color = pv.id_color
        WHERE pv.deleted = 0
        ORDER BY pv.id_product, sz.id_size, cl.id_color');

    $storeIds = array_values(array_map(function ($f) {
        return $f['id_store'];
    }, DIST_FRAGMENTS));
    $placeholders = implode(', ', array_map('intval', $storeIds));

    $stock = store_rows($pdo, "
        SELECT i.id_variant, i.id_store, i.stock
        FROM dbo.inventory AS i
        WHERE i.deleted = 0 AND i.id_store IN ($placeholders)");

    $stores = store_rows($pdo, "
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
        /* Mismo cálculo que dbo.sp_crear_pedido para el precio de outlet. */
        $p['outlet_percent'] = $p['outlet_percent'] !== null ? (int)$p['outlet_percent'] : null;
        $p['sale_price'] = $p['outlet_percent'] !== null
            ? round($p['base_price'] * (100 - $p['outlet_percent']) / 100, 2)
            : $p['base_price'];
        $p['rating'] = (float)$p['rating'];
        $p['reviews'] = (int)$p['reviews'];
        $p['sold'] = (int)$p['sold'];
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
    }, store_rows($pdo, '
        SELECT code, name, discount_percent, free_shipping_from, delivery_days, max_units_per_item,
               support_minutes, can_manage_store
        FROM dbo.account_tier
        ORDER BY id_account_tier'));

    return [
        'products' => $products,
        'stores' => $stores,
        'online_store' => STORE_ONLINE_ID,
        'shipping' => ['free_from' => STORE_FREE_SHIPPING_FROM, 'cost' => STORE_SHIPPING_COST],
        'gift_wrap' => ['cost' => STORE_GIFT_COST],
        'photos' => store_photo_files(),
        'account_tiers' => $tiers,
    ];
}

/* ---------------------------------------------------------------------
   Reseñas
   --------------------------------------------------------------------- */
function store_reviews(int $idProduct): array
{
    $reviews = store_rows(store_db(), '
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

function store_create_review(array $body): array
{
    $pdo = store_db();
    $idCustomer = store_require_customer($pdo);

    $idProduct = (int)($body['id_product'] ?? 0);
    $rating = (int)($body['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        throw new StoreHttpException(400, 'La calificación debe ser de 1 a 5 estrellas.');
    }
    $title = store_text($body, 'title', 3, 80, 'El título');
    $comment = store_text($body, 'comment', 0, 1000, 'El comentario');

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
            throw new StoreHttpException(409, 'Ya publicaste una reseña de este producto.');
        }
        throw $ex;
    }
    if ($stmt->rowCount() === 0) {
        throw new StoreHttpException(404, 'El producto no existe.');
    }
    return ['id_review' => (int)$pdo->lastInsertId()];
}

/* ---------------------------------------------------------------------
   Cuentas
   --------------------------------------------------------------------- */
function store_register(array $body): array
{
    $data = store_validate_registration($body);
    $pdo = store_db();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dbo.customer (id_loyalty_level, id_store, id_address, first_name, last_name_paternal,
                                      last_name_maternal, birth_date, sex, phone, email, accepts_marketing, source_node)
            VALUES (1, :id_store, NULL, :first_name, :last_name_paternal, :last_name_maternal,
                    :birth_date, :sex, :phone, :email, :accepts_marketing, 'web_node')");
        $stmt->bindValue(':id_store', STORE_ONLINE_ID, PDO::PARAM_INT);
        foreach (['first_name', 'last_name_paternal', 'last_name_maternal', 'birth_date', 'sex', 'phone', 'email'] as $field) {
            $stmt->bindValue(':' . $field, $data[$field], $data[$field] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $stmt->bindValue(':accepts_marketing', $data['accepts_marketing'], PDO::PARAM_INT);
        $stmt->execute();
        $idCustomer = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('
            INSERT INTO dbo.customer_credential (id_customer, password_hash, terms_accepted_at)
            VALUES (:id, :hash, SYSUTCDATETIME())');
        $stmt->bindValue(':id', $idCustomer, PDO::PARAM_INT);
        $stmt->bindValue(':hash', $data['password_hash'], PDO::PARAM_STR);
        $stmt->execute();

        $token = store_create_session($pdo, $idCustomer);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
            throw new StoreHttpException(409, 'Ya existe una cuenta con ese correo o teléfono.');
        }
        throw $e;
    }

    notify_safely(function () use ($pdo, $idCustomer, $data) {
        notify_welcome($pdo, $idCustomer, $data['first_name']);
    });

    return ['token' => $token, 'customer' => store_profile($pdo, $idCustomer)];
}

function store_login(array $body): array
{
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');
    if ($email === '' || $password === '') {
        throw new StoreHttpException(400, 'Escribe tu correo y contraseña.');
    }

    $pdo = store_db();
    $rows = store_rows($pdo, '
        SELECT c.id_customer, cr.password_hash, cr.failed_attempts,
               CASE WHEN cr.locked_until > SYSUTCDATETIME() THEN 1 ELSE 0 END AS is_locked
        FROM dbo.customer AS c
        JOIN dbo.customer_credential AS cr ON cr.id_customer = c.id_customer
        WHERE c.email = :email AND c.deleted = 0', [':email' => $email]);

    /* Mismo mensaje si el correo no existe o la contraseña es incorrecta. */
    $invalid = new StoreHttpException(401, 'Correo o contraseña incorrectos.');
    if (!$rows) {
        /* Se calcula un hash igual para que la respuesta tarde lo mismo exista o no el correo. */
        password_verify($password, password_hash('jam-timing-equalizer', PASSWORD_DEFAULT));
        throw $invalid;
    }
    $account = $rows[0];
    $idCustomer = (int)$account['id_customer'];

    if ((int)$account['is_locked'] === 1) {
        throw new StoreHttpException(429, 'Demasiados intentos fallidos. Intenta de nuevo en ' . STORE_LOCK_MINUTES . ' minutos.');
    }

    if (!password_verify($password, $account['password_hash'])) {
        $stmt = $pdo->prepare('
            UPDATE dbo.customer_credential
            SET failed_attempts = CASE WHEN failed_attempts + 1 >= :max_attempts THEN 0 ELSE failed_attempts + 1 END,
                locked_until = CASE WHEN failed_attempts + 1 >= :max_attempts_lock
                                    THEN DATEADD(MINUTE, :lock_minutes, SYSUTCDATETIME()) ELSE locked_until END,
                updated_at = SYSUTCDATETIME()
            WHERE id_customer = :id');
        $stmt->bindValue(':max_attempts', STORE_MAX_FAILED_LOGINS, PDO::PARAM_INT);
        $stmt->bindValue(':max_attempts_lock', STORE_MAX_FAILED_LOGINS, PDO::PARAM_INT);
        $stmt->bindValue(':lock_minutes', STORE_LOCK_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idCustomer, PDO::PARAM_INT);
        $stmt->execute();
        throw $invalid;
    }

    $newHash = password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)
        ? password_hash($password, PASSWORD_DEFAULT)
        : $account['password_hash'];
    $stmt = $pdo->prepare('
        UPDATE dbo.customer_credential
        SET failed_attempts = 0, locked_until = NULL, password_hash = :hash, updated_at = SYSUTCDATETIME()
        WHERE id_customer = :id');
    $stmt->bindValue(':hash', $newHash, PDO::PARAM_STR);
    $stmt->bindValue(':id', $idCustomer, PDO::PARAM_INT);
    $stmt->execute();

    return ['token' => store_create_session($pdo, $idCustomer), 'customer' => store_profile($pdo, $idCustomer)];
}

function store_logout(): array
{
    $token = store_bearer_token();
    if ($token !== null) {
        $stmt = store_db()->prepare('UPDATE dbo.customer_session SET revoked = 1 WHERE token_hash = :token_hash');
        $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->execute();
    }
    return ['logged_out' => true];
}

function store_my_orders(): array
{
    $pdo = store_db();
    $idCustomer = store_require_customer($pdo);

    $orders = store_rows($pdo, "
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
    $items = store_rows($pdo, "
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

    $history = store_rows($pdo, "
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
   Códigos postales
   --------------------------------------------------------------------- */
function store_postal_code(string $zip): array
{
    $rows = store_rows(store_db(), '
        SELECT pc.zip_code, pc.settlement, pc.settlement_type, pc.municipality, pc.city, pc.id_state, s.name AS state
        FROM dbo.postal_code AS pc
        JOIN dbo.state AS s ON s.id_state = pc.id_state
        WHERE pc.zip_code = :zip
        ORDER BY pc.settlement', [':zip' => $zip]);
    if (!$rows) {
        throw new StoreHttpException(404, 'No tenemos registrado ese código postal. Escribe la colonia, la ciudad y el estado.');
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

/* Productos que aparecen en los mismos pedidos que el producto indicado.
   Si hay pocos, se completan con los más vendidos del mismo departamento. */
function store_also_bought(int $idProduct): array
{
    $pdo = store_db();
    $rows = store_rows($pdo, "
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
        $fill = store_rows($pdo, "
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

/* ---------------------------------------------------------------------
   Enrutamiento
   --------------------------------------------------------------------- */
function store_handle(string $method, string $uri, array $body): bool
{
    try {
        if ($method === 'GET' && $uri === '/store/catalog') {
            store_ok(200, store_catalog(), 'Catálogo');
        } elseif ($method === 'GET' && preg_match('#^/store/products/(\d+)/reviews$#', $uri, $m)) {
            store_ok(200, store_reviews((int)$m[1]), 'Reseñas');
        } elseif ($method === 'GET' && preg_match('#^/store/products/(\d+)/also-bought$#', $uri, $m)) {
            store_ok(200, store_also_bought((int)$m[1]), 'También compraron');
        } elseif ($method === 'GET' && preg_match('#^/postal-codes/(\d{5})$#', $uri, $m)) {
            store_ok(200, store_postal_code($m[1]), 'Código postal');
        } elseif ($method === 'POST' && $uri === '/store/reviews') {
            store_ok(201, store_create_review($body), 'Reseña publicada');
        } elseif ($method === 'POST' && $uri === '/auth/register') {
            store_ok(201, store_register($body), 'Cuenta creada');
        } elseif ($method === 'POST' && $uri === '/auth/login') {
            store_ok(200, store_login($body), 'Sesión iniciada');
        } elseif ($method === 'POST' && $uri === '/auth/logout') {
            store_ok(200, store_logout(), 'Sesión cerrada');
        } elseif ($method === 'GET' && $uri === '/auth/me') {
            $pdo = store_db();
            store_ok(200, store_profile($pdo, store_require_customer($pdo)), 'Perfil');
        } elseif ($method === 'GET' && $uri === '/me/orders') {
            store_ok(200, store_my_orders(), 'Pedidos del cliente');
        } else {
            return false;
        }
    } catch (StoreHttpException $e) {
        api_send_error(api_error($e->status, $e->getMessage()));
    } catch (PDOException $e) {
        api_send_error(api_db_error($e));
    } catch (Throwable $e) {
        error_log('Error en la tienda ' . $uri . ': ' . $e->getMessage());
        api_send_error(api_error(500, 'Error interno de la tienda.'));
    }
    return true;
}
