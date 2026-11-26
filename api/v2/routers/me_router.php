<?php
/**
 * Rutas: Pedidos y tickets de soporte del cliente con sesion.
 * Layer: routers. Solo registra rutas; la logica esta en MeController y en services.
 */
final class MeRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('GET', '/me/orders', MeController::class,
            static fn(MeController $c, array $p, string $body): Response
                => ok_response($c->orders(), 'Pedidos del cliente'),
            false);

        $router->add_route('GET', '/support/tickets', MeController::class,
            static fn(MeController $c, array $p, string $body): Response
                => ok_response($c->tickets(), 'Tickets de soporte'),
            false);

        $router->add_route('POST', '/support/tickets', MeController::class,
            static fn(MeController $c, array $p, string $body): Response
                => ok_response($c->create_ticket($body), 'Ticket creado', 201),
            false);
    }
}
