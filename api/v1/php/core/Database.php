<?php
try {
    $DB_Connector = new PDO(
        'mysql:host=localhost;dbname=JAM_db;charset=utf8mb4',
        'root',
        ''
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