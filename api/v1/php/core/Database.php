<?php
$serverName = 'jonathan\SQLEXPRESS';
$database = "JAM_db";

try {
    // Usando el driver nativo PDO para SQL Server
    $DB_Connector = new PDO("sqlsrv:Server=$serverName;Database=$database");
    $DB_Connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);

    // Registra el error interno en los logs del servidor (útil para depurar)
    error_log("Error de conexión a la BD: " . $e->getMessage());

    // Devuelve un mensaje genérico y seguro al cliente de la API
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Error interno de conexión a la base de datos.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>