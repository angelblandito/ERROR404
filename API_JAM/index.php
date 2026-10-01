<?php
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Credenciales para tu SQL Server 2022
$serverName = "localhost\SQLEXPRESS";
$database   = "jam";
$username   = "sa";
$password   = "administrador1";

try {
    $dsn = "odbc:Driver={ODBC Driver 17 for SQL Server};Server=$serverName;Database=$database;";
    $DB_Connector = new PDO($dsn, $username, $password); 
    $DB_Connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => 'False',
        'data' => null,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method == 'GET' && (strpos($uri, '/healthcheck') !== false || (isset($_GET['endpoint']) && $_GET['endpoint'] == 'healthcheck'))){
    http_response_code(200);
    echo json_encode([
        'success' => 'True',
        'data' => ['Language' => 'PHP', 'Database' => 'SQL Server 2022'],
        'message' => 'API online y conectada a JAM'
    ], JSON_UNESCAPED_UNICODE);
}
else if ($method == 'GET' && (strpos($uri, '/Ventas') !== false || (isset($_GET['endpoint']) && $_GET['endpoint'] == 'Ventas'))){
    try {
        // Consulta con LEFT JOIN para obtener el nombre completo del cliente
        $sql_Query = "SELECT v.venta_id, v.folio, ISNULL(c.nombre + ' ' + c.apellido, 'Venta Mostrador / Anónima') AS cliente, 
                             v.tipo_venta, v.subtotal, v.impuesto, v.total, v.estado_pedido, v.fecha_venta
                      FROM Ventas v
                      LEFT JOIN Clientes c ON v.cliente_id = c.cliente_id
                      ORDER BY v.venta_id ASC";

        $stmt = $DB_Connector->prepare($sql_Query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => 'True',
            'data' => $data,
            'message' => 'Lista de ventas obtenida con éxito'
       ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => 'False',
            'data' => null,
            'message' => 'Query error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
else {
    http_response_code(404);
    echo json_encode([
        'success' => 'False',
        'data' => null,
        'message' => 'Endpoint no encontrado: ' . $uri
    ], JSON_UNESCAPED_UNICODE);
}
?>