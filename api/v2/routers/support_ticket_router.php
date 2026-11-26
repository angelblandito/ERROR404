<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de support_ticket. Traduccion de routers/<entidad>_router.py del profe. */
final class SupportTicketRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/support_ticket', SupportTicketController::class, 'Support ticket', false);
    }
}
