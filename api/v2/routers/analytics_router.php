<?php
/**
 * Rutas: Registro de visitas, busquedas e instalaciones (publico) y reportes (administrador).
 * Layer: routers. Solo registra rutas; la logica esta en AnalyticsController y en services.
 */
final class AnalyticsRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('POST', '/analytics/ping', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->ping($body), 'Visita registrada'),
            true);

        $router->add_route('POST', '/analytics/leave', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->leave($body), 'Visita cerrada'),
            true);

        $router->add_route('POST', '/analytics/search', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->search($body), 'Búsqueda registrada', 201),
            true);

        $router->add_route('POST', '/analytics/install', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->install($body), 'Instalación registrada', 201),
            true);

        $router->add_route('GET', '/admin/analytics', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->report(), 'Visitas'),
            false);

        $router->add_route('GET', '/admin/analytics/online', AnalyticsController::class,
            static fn(AnalyticsController $c, array $p, string $body): Response
                => ok_response($c->online(), 'Conectados'),
            false);
    }
}
