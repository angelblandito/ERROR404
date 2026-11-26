<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de season_theme. Traduccion de routers/<entidad>_router.py del profe. */
final class SeasonThemeRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/season_theme', SeasonThemeController::class, 'Season theme', false, ['GET']);
    }
}
