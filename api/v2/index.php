<?php
/**
 * Punto de entrada de la API JAM (equivale a main.py del profe).
 * Carga el codigo, registra los routers y, despues de cada escritura correcta, refresca los
 * fragmentos de las sucursales y copia los cambios al espejo en Azure SQL.
 * Layer: entrypoint.
 */
require_once __DIR__ . '/bootstrap.php';

run_api(
    static fn(string $token): array => TokenService::decode_token($token),
    static function (Router $router): void {
        register_all($router, __DIR__ . '/routers');
    },
    static function (string $method, string $path, int $status): void {
        // Solo despues de escrituras que salieron bien. La sincronizacion y la copia manual ya
        // hacen ese trabajo, y las visitas no cambian los datos de la tienda.
        if ($method === 'GET' || $status >= 400 || in_array($path, ['/distributed/sync', '/mirror/sync'], true)
            || str_starts_with($path, '/analytics/')) {
            return;
        }
        dist_after_write();
    }
);
