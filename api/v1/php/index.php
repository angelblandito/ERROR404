<?php
require_once("config/headers.php");
require_once("core/Response.php");

// Los avisos de PHP se escriben en el log del servidor, nunca dentro de la respuesta JSON.
ini_set('display_errors', '0');

// Manejo de peticiones preflight OPTIONS (para CORS en navegadores)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// En Apache la API vive en .../api/v1/php/; con "php -S" o en Azure, en la raíz.
$uri = preg_replace('#^.*?/api/v1/php(?=/|$)#', '', $uri);
$uri = $uri === '' ? '/' : $uri;
$resourceId = explode('/', $uri)[2] ?? null;
$subResourceId = explode('/', $uri)[3] ?? null; // second key part (only used by store_config)

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (in_array($method, ['POST', 'PUT'], true) && trim($rawBody) !== '' && !is_array($body)) {
    api_send_error(api_error(400, 'El cuerpo de la petición no es un JSON válido.'));
    exit;
}
$body = is_array($body) ? $body : [];

require_once("core/Database.php"); // Database Connection

// Variables de resultado: las asignan los archivos models/*.php incluidos con require_once.
// Se declaran aquí para que siempre existan (y para que el editor no las marque como indefinidas).
$DB_Error = null;
$Checkout = null;
$Elements = [];
$newData = null;
$AbsenceTypeId = null;
$AddressId = null;
$AuditLogId = null;
$CategoryId = null;
$ColorId = null;
$CustomerId = null;
$CustomerOrderId = null;
$EmployeeAbsenceId = null;
$EmployeeId = null;
$EmployeeScheduleId = null;
$InventoryId = null;
$LoyaltyLevelId = null;
$MessageLogId = null;
$OrderItemId = null;
$OwnerId = null;
$PaymentId = null;
$ProductId = null;
$ProductVariantId = null;
$PromotionId = null;
$RoleId = null;
$SizeId = null;
$StateId = null;
$StoreConfigId = null;
$StoreId = null;
$StoreTypeId = null;
$VerificationCodeId = null;

require_once("models/distributed.php"); // Endpoints de la base de datos distribuida
require_once("models/storefront.php");  // Tienda en línea: catálogo, cuentas y reseñas
require_once("models/accounts.php");    // Comunicados y soporte según el tipo de cuenta
require_once("models/admin.php");       // Administración de la tienda

/* Rutas abiertas sin sesión; el resto del CRUD genérico es solo para administradores. */
function api_public_route(string $method, string $uri): bool
{
    if ($uri === '/health-check' || ($method === 'POST' && $uri === '/orders/checkout')) {
        return true;
    }
    return $method === 'GET' && in_array($uri, ['/season_theme', '/season_theme/current', '/promotion', '/state'], true);
}

function api_distributed_route(string $uri): bool
{
    return $uri === '/nodes' || strpos($uri, '/distributed/') === 0 || strpos($uri, '/replication/') === 0;
}

/* Si la sesión no es de administrador responde 401/403 y devuelve false. */
function api_admin_allowed(): bool
{
    $allowed = false;
    store_run('auth', function () use (&$allowed) {
        store_require_admin(store_db());
        $allowed = true;
    });
    return $allowed;
}

if (api_distributed_route($uri) && !api_admin_allowed()) {
    exit;
}

if (dist_handle($method, $uri)) {
    exit;
}

// En la nube la base es una réplica: solo se permiten lecturas.
if ((string)jam_env('JAM_READ_ONLY', '0') === '1' && $method !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Este nodo es una réplica de solo lectura. Los pedidos se registran en el nodo central.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($DB_Connector === null && $uri !== '/health-check') {
    http_response_code(503);

    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => $DB_ConnectionError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (store_handle($method, $uri, $body) || account_handle($method, $uri, $body) || admin_handle($method, $uri, $body)) {
    exit;
}

if (!api_public_route($method, $uri) && !api_admin_allowed()) {
    exit;
}

if ($method === 'GET' && $uri === '/health-check') {

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => [
            'language' => 'php',
            'node' => JAM_DEFAULT_NODE,
            'database' => $DB_Connector !== null ? 'en_linea' : 'sin_conexion',
            'pdo_sqlsrv' => extension_loaded('pdo_sqlsrv')
        ],
        'message' => 'API online'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/orders/checkout') {
    require_once("models/checkout.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $Checkout,
        'message' => 'Pedido creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/season_theme/current') {
    require_once("models/season_themecurrent.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Temporada vigente'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/season_theme') {
    require_once("models/season_themeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/role') {
    require_once("models/roleselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/role') {
    require_once("models/rolepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $RoleId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/role/' . $resourceId) {
    require_once("models/roleput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/role/' . $resourceId) {
    require_once("models/roledelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/store_type') {
    require_once("models/store_typeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/store_type') {
    require_once("models/store_typepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreTypeId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/store_type/' . $resourceId) {
    require_once("models/store_typeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/store_type/' . $resourceId) {
    require_once("models/store_typedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/category') {
    require_once("models/categoryselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/category') {
    require_once("models/categorypost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CategoryId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/category/' . $resourceId) {
    require_once("models/categoryput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/category/' . $resourceId) {
    require_once("models/categorydelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/size') {
    require_once("models/sizeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/size') {
    require_once("models/sizepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $SizeId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/size/' . $resourceId) {
    require_once("models/sizeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/size/' . $resourceId) {
    require_once("models/sizedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/color') {
    require_once("models/colorselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/color') {
    require_once("models/colorpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ColorId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/color/' . $resourceId) {
    require_once("models/colorput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/color/' . $resourceId) {
    require_once("models/colordelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/loyalty_level') {
    require_once("models/loyalty_levelselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/loyalty_level') {
    require_once("models/loyalty_levelpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $LoyaltyLevelId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/loyalty_level/' . $resourceId) {
    require_once("models/loyalty_levelput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/loyalty_level/' . $resourceId) {
    require_once("models/loyalty_leveldelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/absence_type') {
    require_once("models/absence_typeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/absence_type') {
    require_once("models/absence_typepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AbsenceTypeId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/absence_type/' . $resourceId) {
    require_once("models/absence_typeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/absence_type/' . $resourceId) {
    require_once("models/absence_typedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/state') {
    require_once("models/stateselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/state') {
    require_once("models/statepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StateId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/state/' . $resourceId) {
    require_once("models/stateput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/state/' . $resourceId) {
    require_once("models/statedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/owner') {
    require_once("models/ownerselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/owner') {
    require_once("models/ownerpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $OwnerId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/owner/' . $resourceId) {
    require_once("models/ownerput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/owner/' . $resourceId) {
    require_once("models/ownerdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/address') {
    require_once("models/addressselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/address') {
    require_once("models/addresspost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AddressId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/address/' . $resourceId) {
    require_once("models/addressput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/address/' . $resourceId) {
    require_once("models/addressdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/store') {
    require_once("models/storeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/store') {
    require_once("models/storepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/store/' . $resourceId) {
    require_once("models/storeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/store/' . $resourceId) {
    require_once("models/storedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/store_config') {
    require_once("models/store_configselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/store_config') {
    require_once("models/store_configpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreConfigId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/store_config/' . $resourceId . '/' . $subResourceId) {
    require_once("models/store_configput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/store_config/' . $resourceId . '/' . $subResourceId) {
    require_once("models/store_configdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/employee') {
    require_once("models/employeeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/employee') {
    require_once("models/employeepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/employee/' . $resourceId) {
    require_once("models/employeeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/employee/' . $resourceId) {
    require_once("models/employeedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/employee_schedule') {
    require_once("models/employee_scheduleselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/employee_schedule') {
    require_once("models/employee_schedulepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeScheduleId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/employee_schedule/' . $resourceId) {
    require_once("models/employee_scheduleput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/employee_schedule/' . $resourceId) {
    require_once("models/employee_scheduledelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/employee_absence') {
    require_once("models/employee_absenceselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/employee_absence') {
    require_once("models/employee_absencepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeAbsenceId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/employee_absence/' . $resourceId) {
    require_once("models/employee_absenceput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/employee_absence/' . $resourceId) {
    require_once("models/employee_absencedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/verification_code') {
    require_once("models/verification_codeselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/verification_code') {
    require_once("models/verification_codepost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $VerificationCodeId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/verification_code/' . $resourceId) {
    require_once("models/verification_codeput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/verification_code/' . $resourceId) {
    require_once("models/verification_codedelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/customer') {
    require_once("models/customerselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/customer') {
    require_once("models/customerpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CustomerId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/customer/' . $resourceId) {
    require_once("models/customerput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/customer/' . $resourceId) {
    require_once("models/customerdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/product') {
    require_once("models/productselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/product') {
    require_once("models/productpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProductId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/product/' . $resourceId) {
    require_once("models/productput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/product/' . $resourceId) {
    require_once("models/productdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/product_variant') {
    require_once("models/product_variantselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/product_variant') {
    require_once("models/product_variantpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProductVariantId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/product_variant/' . $resourceId) {
    require_once("models/product_variantput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/product_variant/' . $resourceId) {
    require_once("models/product_variantdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/inventory') {
    require_once("models/inventoryselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/inventory') {
    require_once("models/inventorypost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $InventoryId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/inventory/' . $resourceId) {
    require_once("models/inventoryput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/inventory/' . $resourceId) {
    require_once("models/inventorydelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/promotion') {
    require_once("models/promotionselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/promotion') {
    require_once("models/promotionpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $PromotionId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/promotion/' . $resourceId) {
    require_once("models/promotionput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/promotion/' . $resourceId) {
    require_once("models/promotiondelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/customer_order') {
    require_once("models/customer_orderselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/customer_order') {
    require_once("models/customer_orderpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CustomerOrderId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/customer_order/' . $resourceId) {
    require_once("models/customer_orderput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/customer_order/' . $resourceId) {
    require_once("models/customer_orderdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/order_item') {
    require_once("models/order_itemselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/order_item') {
    require_once("models/order_itempost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $OrderItemId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/order_item/' . $resourceId) {
    require_once("models/order_itemput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/order_item/' . $resourceId) {
    require_once("models/order_itemdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/payment') {
    require_once("models/paymentselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/payment') {
    require_once("models/paymentpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $PaymentId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/payment/' . $resourceId) {
    require_once("models/paymentput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/payment/' . $resourceId) {
    require_once("models/paymentdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/message_log') {
    require_once("models/message_logselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/message_log') {
    require_once("models/message_logpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $MessageLogId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/message_log/' . $resourceId) {
    require_once("models/message_logput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/message_log/' . $resourceId) {
    require_once("models/message_logdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'GET' && $uri === '/audit_log') {
    require_once("models/audit_logselect.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Consulta realizada'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'POST' && $uri === '/audit_log') {
    require_once("models/audit_logpost.php");
    api_fail_if_error();
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AuditLogId,
        'message' => 'Registro creado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'PUT' && $uri === '/audit_log/' . $resourceId) {
    require_once("models/audit_logput.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro actualizado'
    ], JSON_UNESCAPED_UNICODE);

} else if ($method === 'DELETE' && $uri === '/audit_log/' . $resourceId) {
    require_once("models/audit_logdelete.php");
    api_fail_if_error();
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Registro eliminado'
    ], JSON_UNESCAPED_UNICODE);

} else {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Recurso no encontrado'
    ], JSON_UNESCAPED_UNICODE);
}
?>