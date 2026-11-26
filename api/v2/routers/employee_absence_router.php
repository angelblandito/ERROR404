<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de employee_absence. Traduccion de routers/<entidad>_router.py del profe. */
final class EmployeeAbsenceRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/employee_absence', EmployeeAbsenceController::class, 'Employee absence', false);
    }
}
