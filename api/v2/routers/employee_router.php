<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de employee. Traduccion de routers/<entidad>_router.py del profe. */
final class EmployeeRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/employee', EmployeeController::class, 'Employee', false);
    }
}
