<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de market_price. Traduccion de routers/<entidad>_router.py del profe. */
final class MarketPriceRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/market_price', MarketPriceController::class, 'Market price', false);
    }
}
