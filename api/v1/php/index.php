<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

// 1. Conexión a tu base de datos SQL Server (jam)
require_once("core/Database.php"); 

if ($method === 'GET' && strpos($uri, '/health-check') !== false) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'language' => 'php'
        ],
        'message' => 'API online'
    ]);

} 
// =========================================================================
// ENDPOINT 1: PROVEEDORES 
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/proveedores') !== false) {
    require_once("models/proveedores_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements, 
        'message' => 'Proveedores obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
} 
// =========================================================================
// ENDPOINT 2: VENTAS 
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/ventas') !== false) {
    require_once("models/ventas_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Ventas obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 3: CLIENTES
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/clientes') !== false) {
    require_once("models/clientes_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Clientes obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 4: PRODUCTOS
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/productos') !== false) {
    require_once("models/productos_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Productos obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 5: ORDENES DE COMPRA
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/ordenes_compra') !== false) {
    require_once("models/ordenes_compra_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Órdenes de compra obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 6: CUPONES DE DESCUENTO (Usando el nombre exacto de tu archivo)
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/cupones_descuentos') !== false) {
    require_once("models/cupones_descuentos_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Cupones obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 7: RESEÑAS DE PRODUCTOS
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/resenas_productos') !== false) {
    require_once("models/resenas_productos_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Reseñas obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 8: DEVOLUCIONES
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/devoluciones') !== false) {
    require_once("models/devoluciones_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Devoluciones obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 9: SUCURSALES
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/sucursales') !== false) {
    require_once("models/sucursales_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Sucursales obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 10: CATEGORIAS
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/categorias') !== false) {
    require_once("models/categorias_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Categorías obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 11: METODOS DE PAGO
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/metodos_pago') !== false) {
    require_once("models/metodos_pago_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Métodos de pago obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 12: INVENTARIO SUCURSALES
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/inventario_sucursales') !== false) {
    require_once("models/inventario_sucursales_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Inventario obtenido con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 13: EMPLEADOS
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/empleados') !== false) {
    require_once("models/empleados_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Empleados obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 14: DETALLES VENTA
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/detalles_venta') !== false) {
    require_once("models/detalles_venta_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Detalles de venta obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 15: ENVIOS
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/envios') !== false) {
    require_once("models/envios_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Envíos obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ENDPOINT 16: COMPRAS A PROVEEDORES
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/compras_proveedores') !== false) {
    require_once("models/compras_proveedores_select.php"); 
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Compras a proveedores obtenidas con éxito'
    ], JSON_UNESCAPED_UNICODE);
}
// =========================================================================
// ERROR 404: RUTA NO ENCONTRADA
// =========================================================================
else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Not Found - El endpoint no existe en la API', 
        'debug' => [
            'method' => $method,
            'uri' => $uri,
            'body' => $body
        ]
    ]);
}
?>