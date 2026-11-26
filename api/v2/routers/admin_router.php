<?php
/**
 * Rutas: Panel de administracion de la tienda (solo administradores).
 * Layer: routers. Solo registra rutas; la logica esta en AdminController y en services.
 */
final class AdminRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('GET', '/admin/summary', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->summary(), 'Resumen'),
            false);

        $router->add_route('GET', '/admin/customers', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->customers(), 'Clientes'),
            false);

        $router->add_route('PUT', '/admin/customers/{id}/tier', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->set_tier((int) $p['id'], $body), 'Tipo de cuenta actualizado'),
            false);

        $router->add_route('POST', '/admin/customers/evaluate', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->evaluate_priority(), 'Regla de clientes prioritarios aplicada'),
            false);

        $router->add_route('GET', '/admin/orders', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->orders(), 'Pedidos'),
            false);

        $router->add_route('PUT', '/admin/orders/{id}/status', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->set_order_status((int) $p['id'], $body), 'Estado del pedido actualizado'),
            false);

        $router->add_route('PUT', '/admin/products/{id}', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->set_price((int) $p['id'], $body), 'Precio actualizado'),
            false);

        $router->add_route('PUT', '/admin/inventory', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->set_stock($body), 'Existencias actualizadas'),
            false);

        $router->add_route('GET', '/admin/tickets', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->tickets(), 'Tickets de soporte'),
            false);

        $router->add_route('PUT', '/admin/tickets/{id}', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->answer_ticket((int) $p['id'], $body), 'Ticket actualizado'),
            false);

        $router->add_route('GET', '/admin/announcements', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->announcements(), 'Comunicados'),
            false);

        $router->add_route('POST', '/admin/announcements', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->create_announcement($body), 'Comunicado publicado', 201),
            false);

        $router->add_route('DELETE', '/admin/announcements/{id}', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->delete_announcement((int) $p['id']), 'Comunicado eliminado'),
            false);

        $router->add_route('GET', '/admin/emails', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->emails(), 'Correos'),
            false);

        $router->add_route('GET', '/admin/market', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->market(), 'Precios de mercado'),
            false);

        $router->add_route('POST', '/admin/market', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->create_market_price($body), 'Precio de mercado registrado', 201),
            false);

        $router->add_route('PUT', '/admin/market/{id}', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->update_market_price((int) $p['id'], $body), 'Precio de mercado actualizado'),
            false);

        $router->add_route('DELETE', '/admin/market/{id}', AdminController::class,
            static fn(AdminController $c, array $p, string $body): Response
                => ok_response($c->delete_market_price((int) $p['id']), 'Precio de mercado eliminado'),
            false);
    }
}
