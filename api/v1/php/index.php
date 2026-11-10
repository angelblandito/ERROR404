<?php
require_once("config/headers.php");

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$resourceId = explode('/', $uri)[2] ?? null;

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

require_once("core/Database.php"); // Database Conenction

if ($method === 'GET' && $uri === '/health-check') {

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => [
            'language' => 'php'
        ],
        'message' => 'API online'
    ]);

} else if ($method === 'GET' && $uri === '/sucursales') {
    require_once("models/sucursalesselect.php");
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

} else if ($method === 'POST' && $uri === '/sucursales') {
    require_once("models/sucursalespost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $SucursalId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/sucursales/' . $resourceId) {
    require_once("models/sucursalesput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/sucursales/' . $resourceId) {
    require_once("models/sucursalesdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/categorias') {
    require_once("models/categoriasselect.php");
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

} else if ($method === 'POST' && $uri === '/categorias') {
    require_once("models/categoriaspost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CategoriaId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/categorias/' . $resourceId) {
    require_once("models/categoriasput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/categorias/' . $resourceId) {
    require_once("models/categoriasdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/proveedores') {
    require_once("models/proveedoresselect.php");
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

} else if ($method === 'POST' && $uri === '/proveedores') {
    require_once("models/proveedorespost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProveedorId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/proveedores/' . $resourceId) {
    require_once("models/proveedoresput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/proveedores/' . $resourceId) {
    require_once("models/proveedoresdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/metodos_pago') {
    require_once("models/metodos_pagoselect.php");
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

} else if ($method === 'POST' && $uri === '/metodos_pago') {
    require_once("models/metodos_pagopost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $MetodoId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/metodos_pago/' . $resourceId) {
    require_once("models/metodos_pagoput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/metodos_pago/' . $resourceId) {
    require_once("models/metodos_pagodelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/productos') {
    require_once("models/productosselect.php");
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

} else if ($method === 'POST' && $uri === '/productos') {
    require_once("models/productospost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ProductoId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/productos/' . $resourceId) {
    require_once("models/productosput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/productos/' . $resourceId) {
    require_once("models/productosdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/inventario_sucursales') {
    require_once("models/inventario_sucursalesselect.php");
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

} else if ($method === 'POST' && $uri === '/inventario_sucursales') {
    require_once("models/inventario_sucursalespost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $InventarioId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/inventario_sucursales/' . $resourceId) {
    require_once("models/inventario_sucursalesput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/inventario_sucursales/' . $resourceId) {
    require_once("models/inventario_sucursalesdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/empleados') {
    require_once("models/empleadosselect.php");
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

} else if ($method === 'POST' && $uri === '/empleados') {
    require_once("models/empleadospost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EmpleadoId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/empleados/' . $resourceId) {
    require_once("models/empleadosput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/empleados/' . $resourceId) {
    require_once("models/empleadosdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/clientes') {
    require_once("models/clientesselect.php");
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

} else if ($method === 'POST' && $uri === '/clientes') {
    require_once("models/clientespost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $ClienteId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/clientes/' . $resourceId) {
    require_once("models/clientesput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/clientes/' . $resourceId) {
    require_once("models/clientesdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/ventas') {
    require_once("models/ventasselect.php");
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

} else if ($method === 'POST' && $uri === '/ventas') {
    require_once("models/ventaspost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $VentaId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/ventas/' . $resourceId) {
    require_once("models/ventasput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/ventas/' . $resourceId) {
    require_once("models/ventasdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/detalles_venta') {
    require_once("models/detalles_ventaselect.php");
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

} else if ($method === 'POST' && $uri === '/detalles_venta') {
    require_once("models/detalles_ventapost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $DetalleId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/detalles_venta/' . $resourceId) {
    require_once("models/detalles_ventaput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/detalles_venta/' . $resourceId) {
    require_once("models/detalles_ventadelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/envios') {
    require_once("models/enviosselect.php");
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

} else if ($method === 'POST' && $uri === '/envios') {
    require_once("models/enviospost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $EnvioId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/envios/' . $resourceId) {
    require_once("models/enviosput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/envios/' . $resourceId) {
    require_once("models/enviosdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/compras_proveedores') {
    require_once("models/compras_proveedoresselect.php");
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

} else if ($method === 'POST' && $uri === '/compras_proveedores') {
    require_once("models/compras_proveedorespost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CompraId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/compras_proveedores/' . $resourceId) {
    require_once("models/compras_proveedoresput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/compras_proveedores/' . $resourceId) {
    require_once("models/compras_proveedoresdelete.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'GET' && $uri === '/cupones_descuento') {
    require_once("models/cupones_descuentoselect.php");
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

} else if ($method === 'POST' && $uri === '/cupones_descuento') {
    require_once("models/cupones_descuentopost.php");
    http_response_code(201);

    echo json_encode([
        'success' => true,
        'data' => $CuponId,
        'message' => 'Todo bien',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => $body
        ]
    ]);

} else if ($method === 'PUT' && $uri === '/cupones_descuento/' . $resourceId) {
    require_once("models/cupones_descuentoput.php");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'data' => $newData,
        'message' => 'Todo bien'
    ]);

} else if ($method === 'DELETE' && $uri === '/cupones_descuento/' . $resourceId) {
    require_once("models/cupones_descuentodelete.php");
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