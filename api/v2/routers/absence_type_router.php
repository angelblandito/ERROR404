<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de absence_type. Traduccion de routers/<entidad>_router.py del profe. */
final class AbsenceTypeRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/absence_type', AbsenceTypeController::class, 'Absence type', false);
    }
}
