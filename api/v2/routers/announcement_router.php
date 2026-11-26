<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de announcement. Traduccion de routers/<entidad>_router.py del profe. */
final class AnnouncementRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/announcement', AnnouncementController::class, 'Announcement', false);
    }
}
