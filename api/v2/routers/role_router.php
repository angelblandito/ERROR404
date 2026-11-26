<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de role. Traduccion de routers/<entidad>_router.py del profe. */
final class RoleRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/role', RoleController::class, 'Role', false);
    }
}
