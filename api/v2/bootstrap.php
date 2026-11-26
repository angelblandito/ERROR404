<?php
/**
 * Carga todo el codigo de la API en orden: core, utils, clases base y despues servicios,
 * modelos, schemas y controllers. Lo usan index.php y las pruebas.
 * Los routers no se cargan aqui: register_all() los descubre y los registra.
 */
$core_files = [
    'core/settings.php', 'core/exceptions.php', 'core/http.php', 'utils/date_utils.php', 'core/logger.php',
    'core/database.php', 'core/security.php', 'core/router.php',
    'core/middleware.php', 'core/error_middleware.php', 'core/logging_middleware.php',
    'core/security_middleware.php', 'core/pipeline.php', 'core/app.php',
    'schemas/base_schema.php', 'models/base_model.php',
    'controllers/base_controller.php', 'controllers/api_controller.php', 'controllers/health_controller.php',
    'routers/register_all.php',
];
foreach ($core_files as $file) {
    require_once __DIR__ . '/' . $file;
}

// Nodo que usan la tienda y el CRUD generico (central)
define('JAM_DEFAULT_NODE', Settings::instance()->JAM_DEFAULT_NODE);

foreach (['services', 'models', 'schemas', 'controllers'] as $folder) {
    $files = glob(__DIR__ . "/$folder/*.php") ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        require_once $file;
    }
}
