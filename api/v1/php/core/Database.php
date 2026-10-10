<?php
// Configuración de conexión ODBC a SQL Server
$serverName = 'jonathan\SQLEXPRESS';
$database = "jam";

try {
    // Conexión usando el driver ODBC de SQL Server
    $DB_Connector = new PDO("odbc:Driver={SQL Server};Server=$serverName;Database=$database;DriverCompleteness=1");
    $DB_Connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}
?>