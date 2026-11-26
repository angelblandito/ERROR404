<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de order_item. Traduccion de routers/<entidad>_router.py del profe. */
final class OrderItemRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/order_item', OrderItemController::class, 'Order item', false);
    }
}
