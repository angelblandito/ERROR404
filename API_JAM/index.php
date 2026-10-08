<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Conexión a SQL Server mediante ODBC (Configuración nativa de XAMPP)
$serverName = 'jonathan\SQLEXPRESS';
$database = "jam";

try {
    $DB_Connector = new PDO("odbc:Driver={SQL Server};Server=$serverName;Database=$database;DriverCompleteness=1");
    $DB_Connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => 'False', 'message' => 'Error de conexión: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? 'Todo';

if ($method === 'GET') {
    try {
        // Lista con los nombres de tus tablas
        $tablas = [
            'Ventas',
            'Proveedores',
            'Clientes',
            'Productos',
            'Ordenes_Compra',
            'Cupones_Descuento',
            'Resenas_Productos',
            'Devoluciones'
            // Agrega aquí los nombres de las demás tablas de tu BD según las vayas requiriendo
        ];

        // Si se consulta "Todo"
        if ($endpoint === 'Todo' || empty($endpoint)) {
            $response_data = [];

            foreach ($tablas as $tabla) {
                if ($tabla === 'Ventas') {
                    $sql = "SELECT V.venta_id, V.folio, 
                            ISNULL(C.nombre + ' ' + C.apellido, 'Venta Mostrador / Anónima') AS cliente,
                            V.tipo_venta, V.subtotal, V.impuesto, V.total, V.estado_pedido, V.fecha_venta
                            FROM Ventas V
                            LEFT JOIN Clientes C ON V.cliente_id = C.cliente_id";
                } else {
                    $sql = "SELECT * FROM $tabla";
                }

                $stmt = $DB_Connector->prepare($sql);
                $stmt->execute();
                $response_data[$tabla] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            http_response_code(200);
            echo json_encode([
                'success' => 'True',
                'data' => $response_data,
                'message' => 'Todas las tablas de la base de datos jam obtenidas con éxito'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        } else {
            // Consulta para un endpoint / tabla específica
            if ($endpoint === 'Ventas') {
                $sql = "SELECT V.venta_id, V.folio, 
                        ISNULL(C.nombre + ' ' + C.apellido, 'Venta Mostrador / Anónima') AS cliente,
                        V.tipo_venta, V.subtotal, V.impuesto, V.total, V.estado_pedido, V.fecha_venta
                        FROM Ventas V
                        LEFT JOIN Clientes C ON V.cliente_id = C.cliente_id";
            } else {
                $sql = "SELECT * FROM $endpoint";
            }

            $stmt = $DB_Connector->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'success' => 'True',
                'data' => $data,
                'message' => "Datos de '{$endpoint}' obtenidos con éxito"
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => 'False',
            'data' => null,
            'message' => 'Query error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => 'False',
        'data' => null,
        'message' => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE);
}
?>