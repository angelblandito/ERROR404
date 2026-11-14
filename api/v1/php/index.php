<?php
header('Content-Type: application/json');
// En producción, reemplaza '*' por el dominio exacto de tu frontend (ej. 'http://localhost:3000')
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejo de peticiones preflight OPTIONS (para CORS en navegadores)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Extraer únicamente la ruta path sin parámetros query
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Obtener el endpoint relativo eliminando la carpeta base si tu API está alojada en un subdirectorio
// Ajusta '/ERROR404' o '/api/v1/php' según la estructura exacta de tu carpeta web en Apache
$endpoint = preg_replace('#^.*?/api/v1/php#i', '', $path);
$endpoint = rtrim($endpoint, '/'); // Eliminar barra final sobrante si la hay

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
$Elements = [];

// 1. Cargar la conexión a la base de datos SQL Server
require_once("core/Database.php"); 

// 2. Evaluador de rutas exactas
if ($method === 'GET') {
    switch ($endpoint) {
        case '/health-check':
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => ['language' => 'php'],
                'message' => 'API online'
            ]);
            break;

        case '/proveedores':
            require_once("models/proveedores_select.php"); 
            responderExito($Elements, 'Proveedores obtenidos con éxito');
            break;

        case '/ventas':
            require_once("models/ventas_select.php"); 
            responderExito($Elements, 'Ventas obtenidas con éxito');
            break;

        case '/clientes':
            require_once("models/clientes_select.php"); 
            responderExito($Elements, 'Clientes obtenidos con éxito');
            break;

        case '/productos':
            require_once("models/productos_select.php"); 
            responderExito($Elements, 'Productos obtenidos con éxito');
            break;

        case '/ordenes_compra':
            require_once("models/ordenes_compra_select.php"); 
            responderExito($Elements, 'Órdenes de compra obtenidas con éxito');
            break;

        case '/cupones_descuentos':
            require_once("models/cupones_descuentos_select.php"); 
            responderExito($Elements, 'Cupones obtenidos con éxito');
            break;

        case '/resenas_productos':
            require_once("models/resenas_productos_select.php"); 
            responderExito($Elements, 'Reseñas obtenidas con éxito');
            break;

        case '/devoluciones':
            require_once("models/devoluciones_select.php"); 
            responderExito($Elements, 'Devoluciones obtenidas con éxito');
            break;

        case '/sucursales':
            require_once("models/sucursales_select.php"); 
            responderExito($Elements, 'Sucursales obtenidas con éxito');
            break;

        case '/categorias':
            require_once("models/categorias_select.php"); 
            responderExito($Elements, 'Categorías obtenidas con éxito');
            break;

        case '/metodos_pago':
            require_once("models/metodos_pago_select.php"); 
            responderExito($Elements, 'Métodos de pago obtenidos con éxito');
            break;

        case '/inventario_sucursales':
            require_once("models/inventario_sucursales_select.php"); 
            responderExito($Elements, 'Inventario obtenido con éxito');
            break;

        case '/empleados':
            require_once("models/empleados_select.php"); 
            responderExito($Elements, 'Empleados obtenidos con éxito');
            break;

        case '/detalles_venta':
            require_once("models/detalles_venta_select.php"); 
            responderExito($Elements, 'Detalles de venta obtenidos con éxito');
            break;

        case '/envios':
            require_once("models/envios_select.php"); 
            responderExito($Elements, 'Envíos obtenidos con éxito');
            break;

        case '/compras_proveedores':
            require_once("models/compras_proveedores_select.php"); 
            responderExito($Elements, 'Compras a proveedores obtenidas con éxito');
            break;

        default:
            responderNoEncontrado($method, $endpoint, $body);
            break;
    }
} else {
    // Si envían POST, PUT, DELETE cuando aún no los implementas
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Método HTTP no permitido para este endpoint'
    ], JSON_UNESCAPED_UNICODE);
}

// Funciones auxiliares para simplificar el código
function responderExito($data, $mensaje) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
}

function responderNoEncontrado($method, $endpoint, $body) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Not Found - El endpoint no existe en la API',
        'debug' => [
            'method' => $method,
            'endpoint_parsed' => $endpoint,
            'body' => $body
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>