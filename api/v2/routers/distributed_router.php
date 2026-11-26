<?php
/**
 * Rutas: Base de datos distribuida: nodos, fragmentos, replicacion, sincronizacion y espejo en Azure (solo administradores).
 * Layer: routers. Solo registra rutas; la logica esta en DistributedController y en services.
 */
final class DistributedRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('GET', '/nodes', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->nodes(), 'Estado de los nodos'),
            false);

        $router->add_route('GET', '/distributed/inventory', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->inventory(), 'Inventario reconstruido desde los fragmentos'),
            false);

        $router->add_route('GET', '/distributed/orders', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->orders(), 'Pedidos reconstruidos desde los fragmentos'),
            false);

        $router->add_route('GET', '/distributed/customers', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->customers(), 'Clientes reconstruidos desde los fragmentos verticales'),
            false);

        $router->add_route('GET', '/distributed/fragments', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->fragments(), 'Completitud de los fragmentos'),
            false);

        $router->add_route('GET', '/replication/status', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->replication(), 'Estado de la replicación'),
            false);

        $router->add_route('POST', '/distributed/sync', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->sync(), 'Nodos sincronizados'),
            false);

        $router->add_route('POST', '/mirror/sync', DistributedController::class,
            static fn(DistributedController $c, array $p, string $body): Response
                => ok_response($c->mirror_sync(), 'Espejo actualizado'),
            false);
    }
}
