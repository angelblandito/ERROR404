<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de postal_code. Traduccion de routers/<entidad>_router.py del profe. */
final class PostalCodeRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/postal_code', PostalCodeController::class, 'Postal code', false);
    }
}
