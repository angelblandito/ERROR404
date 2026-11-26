<?php
/**
 * Rutas: Comparador de mercado para cuentas Prioritario y Administrador.
 * La ruta no exige sesion en el router: el servicio la pide con un mensaje propio para la pagina.
 * Layer: routers. Solo registra rutas; la logica esta en MarketController y en services.
 */
final class MarketRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('GET', '/market', MarketController::class,
            static fn(MarketController $c, array $p, string $body): Response
                => ok_response($c->compare(), 'Comparador de mercado'),
            true);
    }
}
