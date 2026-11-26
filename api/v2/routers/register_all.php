<?php
/**
 * Traduccion de routers/__init__.py: descubre y registra los routers sin editar el punto de entrada.
 *
 * Convencion (igual que el profe): cada archivo *_router.php define la clase <Nombre>Router con el
 * metodo estatico register_routes(Router). Agregar un router = agregar su archivo a la carpeta.
 * Se cargan en orden alfabetico.
 * Layer: routers.
 */
function register_all(Router $router, string $routers_dir): void
{
    $files = glob(rtrim($routers_dir, '/') . '/*_router.php') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        require_once $file;
        // health_router.php -> HealthRouter
        $class = implode('', array_map('ucfirst', explode('_', basename($file, '.php'))));
        if (!is_callable([$class, 'register_routes'])) {
            throw new RuntimeException("$class must define register_routes(Router \$router)");
        }
        $class::register_routes($router);
    }
}
