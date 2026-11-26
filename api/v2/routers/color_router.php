<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de color. Traduccion de routers/<entidad>_router.py del profe. */
final class ColorRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/color', ColorController::class, 'Color', false);
    }
}
