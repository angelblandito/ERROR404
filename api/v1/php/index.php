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
// ENDPOINT 1: PROVEEDORES (Reemplaza al '/moods' original)
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/proveedores') !== false) {
    
    // Llama al modelo que creaste en el paso anterior
    require_once("models/proveedores_select.php"); 
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements, // Esta variable se llenó dentro de proveedores_select.php
        'message' => 'Proveedores obtenidos con éxito'
    ], JSON_UNESCAPED_UNICODE);

} 
// =========================================================================
// ENDPOINT 2: VENTAS (Ejemplo de cómo agregar tu segunda tabla)
// =========================================================================
else if ($method === 'GET' && strpos($uri, '/ventas') !== false) {
    
    // Necesitarías crear el archivo ventas_select.php en tu carpeta models
    require_once("models/ventas_select.php"); 
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $Elements,
        'message' => 'Ventas obtenidas con éxito'
    ]);

}

// Aquí puedes ir copiando y pegando más bloques "else if" para tus otras 18 tablas
// ...

else {
    // Si se pide una URL que no existe, muestra el error 404 del profesor
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