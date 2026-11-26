<?php
/**
 * Traduccion de routers/health_router.py. /health y /health-check, publicos (sin token).
 * Layer: routers. Solo registra rutas; no contiene logica.
 */
final class HealthRouter
{
    public static function register_routes(Router $router): void
    {
        $handler = static fn(HealthController $c, array $params, string $body): Response => $c->get();
        $router->add_route('GET', '/health', HealthController::class, $handler, true);
        $router->add_route('GET', '/health-check', HealthController::class, $handler, true);
    }
}
