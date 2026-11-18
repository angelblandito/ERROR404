<?php
require_once("config/headers.php");

// Manejo de peticiones preflight OPTIONS (para CORS en navegadores)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$resourceId = explode('/', $uri)[2] ?? null;
$subResourceId = explode('/', $uri)[3] ?? null; // second key part (only used by store_config)

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

require_once("core/Database.php"); // Database Connection

// Variables de resultado: las asignan los archivos models/*.php incluidos con require_once.
// Se declaran aqui para que siempre existan (y para que el editor no las marque como indefinidas).
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

if ($method === 'GET' && $uri === '/health-check') {

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => [
            'language' => 'php'
        ],
        'message' => 'API online'
    ]);

} else if ($method === 'GET' && $uri === '/role') {
    require_once("models/roleselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/role') {
    require_once("models/rolepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $RoleId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/role/' . $resourceId) {
    require_once("models/roleput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/role/' . $resourceId) {
    require_once("models/roledelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/store_type') {
    require_once("models/store_typeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/store_type') {
    require_once("models/store_typepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreTypeId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/store_type/' . $resourceId) {
    require_once("models/store_typeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/store_type/' . $resourceId) {
    require_once("models/store_typedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/category') {
    require_once("models/categoryselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/category') {
    require_once("models/categorypost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CategoryId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/category/' . $resourceId) {
    require_once("models/categoryput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/category/' . $resourceId) {
    require_once("models/categorydelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/size') {
    require_once("models/sizeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/size') {
    require_once("models/sizepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $SizeId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/size/' . $resourceId) {
    require_once("models/sizeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/size/' . $resourceId) {
    require_once("models/sizedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/color') {
    require_once("models/colorselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/color') {
    require_once("models/colorpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ColorId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/color/' . $resourceId) {
    require_once("models/colorput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/color/' . $resourceId) {
    require_once("models/colordelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/loyalty_level') {
    require_once("models/loyalty_levelselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/loyalty_level') {
    require_once("models/loyalty_levelpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $LoyaltyLevelId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/loyalty_level/' . $resourceId) {
    require_once("models/loyalty_levelput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/loyalty_level/' . $resourceId) {
    require_once("models/loyalty_leveldelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/absence_type') {
    require_once("models/absence_typeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/absence_type') {
    require_once("models/absence_typepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AbsenceTypeId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/absence_type/' . $resourceId) {
    require_once("models/absence_typeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/absence_type/' . $resourceId) {
    require_once("models/absence_typedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/state') {
    require_once("models/stateselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/state') {
    require_once("models/statepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StateId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/state/' . $resourceId) {
    require_once("models/stateput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/state/' . $resourceId) {
    require_once("models/statedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/owner') {
    require_once("models/ownerselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/owner') {
    require_once("models/ownerpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $OwnerId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/owner/' . $resourceId) {
    require_once("models/ownerput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/owner/' . $resourceId) {
    require_once("models/ownerdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/address') {
    require_once("models/addressselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/address') {
    require_once("models/addresspost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AddressId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/address/' . $resourceId) {
    require_once("models/addressput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/address/' . $resourceId) {
    require_once("models/addressdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/store') {
    require_once("models/storeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/store') {
    require_once("models/storepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/store/' . $resourceId) {
    require_once("models/storeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/store/' . $resourceId) {
    require_once("models/storedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/store_config') {
    require_once("models/store_configselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/store_config') {
    require_once("models/store_configpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $StoreConfigId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/store_config/' . $resourceId . '/' . $subResourceId) {
    require_once("models/store_configput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/store_config/' . $resourceId . '/' . $subResourceId) {
    require_once("models/store_configdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/employee') {
    require_once("models/employeeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/employee') {
    require_once("models/employeepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/employee/' . $resourceId) {
    require_once("models/employeeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/employee/' . $resourceId) {
    require_once("models/employeedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/employee_schedule') {
    require_once("models/employee_scheduleselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/employee_schedule') {
    require_once("models/employee_schedulepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeScheduleId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/employee_schedule/' . $resourceId) {
    require_once("models/employee_scheduleput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/employee_schedule/' . $resourceId) {
    require_once("models/employee_scheduledelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/employee_absence') {
    require_once("models/employee_absenceselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/employee_absence') {
    require_once("models/employee_absencepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmployeeAbsenceId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/employee_absence/' . $resourceId) {
    require_once("models/employee_absenceput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/employee_absence/' . $resourceId) {
    require_once("models/employee_absencedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/verification_code') {
    require_once("models/verification_codeselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/verification_code') {
    require_once("models/verification_codepost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $VerificationCodeId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/verification_code/' . $resourceId) {
    require_once("models/verification_codeput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/verification_code/' . $resourceId) {
    require_once("models/verification_codedelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/customer') {
    require_once("models/customerselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/customer') {
    require_once("models/customerpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CustomerId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/customer/' . $resourceId) {
    require_once("models/customerput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/customer/' . $resourceId) {
    require_once("models/customerdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/product') {
    require_once("models/productselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/product') {
    require_once("models/productpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProductId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/product/' . $resourceId) {
    require_once("models/productput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/product/' . $resourceId) {
    require_once("models/productdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/product_variant') {
    require_once("models/product_variantselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/product_variant') {
    require_once("models/product_variantpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProductVariantId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/product_variant/' . $resourceId) {
    require_once("models/product_variantput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/product_variant/' . $resourceId) {
    require_once("models/product_variantdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/inventory') {
    require_once("models/inventoryselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/inventory') {
    require_once("models/inventorypost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $InventoryId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/inventory/' . $resourceId) {
    require_once("models/inventoryput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/inventory/' . $resourceId) {
    require_once("models/inventorydelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/promotion') {
    require_once("models/promotionselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/promotion') {
    require_once("models/promotionpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $PromotionId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/promotion/' . $resourceId) {
    require_once("models/promotionput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/promotion/' . $resourceId) {
    require_once("models/promotiondelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/customer_order') {
    require_once("models/customer_orderselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/customer_order') {
    require_once("models/customer_orderpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CustomerOrderId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/customer_order/' . $resourceId) {
    require_once("models/customer_orderput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/customer_order/' . $resourceId) {
    require_once("models/customer_orderdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/order_item') {
    require_once("models/order_itemselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/order_item') {
    require_once("models/order_itempost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $OrderItemId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/order_item/' . $resourceId) {
    require_once("models/order_itemput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/order_item/' . $resourceId) {
    require_once("models/order_itemdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/payment') {
    require_once("models/paymentselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/payment') {
    require_once("models/paymentpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $PaymentId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/payment/' . $resourceId) {
    require_once("models/paymentput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/payment/' . $resourceId) {
    require_once("models/paymentdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/message_log') {
    require_once("models/message_logselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/message_log') {
    require_once("models/message_logpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $MessageLogId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/message_log/' . $resourceId) {
    require_once("models/message_logput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/message_log/' . $resourceId) {
    require_once("models/message_logdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/audit_log') {
    require_once("models/audit_logselect.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'POST' && $uri === '/audit_log') {
    require_once("models/audit_logpost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $AuditLogId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/audit_log/' . $resourceId) {
    require_once("models/audit_logput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/audit_log/' . $resourceId) {
    require_once("models/audit_logdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Not Found',
        'debug' => [
            'method' => $method,
            'uri' => $uri,
            'body' => $body
        ]
    ]);
}
?>