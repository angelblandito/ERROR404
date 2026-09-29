<?php
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawBody = file_get_contents('php://input');
$budy = json_decode($rawBody, true); 
                   



if ($method == 'GET' && $uri == '/healtcheck'){
    http_response_code(200);
    echo json_encode([
        'success' => 'true',
        'data' => ['Language' => 'PHP'],
        'message' => 'API online'
        ]);
}

else{
    http_response_code(404);
    echo json_encode([
        'success' => 'false',
        'data' => null,
        'message' => 'Not found'
    ]);
}
