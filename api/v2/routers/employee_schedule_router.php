<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de employee_schedule. Traduccion de routers/<entidad>_router.py del profe. */
final class EmployeeScheduleRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/employee_schedule', EmployeeScheduleController::class, 'Employee schedule', false);
    }
}
