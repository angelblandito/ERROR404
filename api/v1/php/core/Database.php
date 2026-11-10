<?php
try {
    $DB_Connector = new PDO(
        'sqlsrv:Server=localhost;Database=jam;TrustServerCertificate=1',
        null,
        null
    );
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