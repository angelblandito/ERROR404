<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de loyalty_level. Traduccion de routers/<entidad>_router.py del profe. */
final class LoyaltyLevelRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/loyalty_level', LoyaltyLevelController::class, 'Loyalty level', false);
    }
}
