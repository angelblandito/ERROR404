<?php
/**
 * Rutas: Compra en linea.
 * La ruta no exige sesion en el router: el servicio la pide con un mensaje propio para la pagina.
 * Layer: routers. Solo registra rutas; la logica esta en CheckoutController y en services.
 */
final class CheckoutRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('POST', '/orders/checkout', CheckoutController::class,
            static fn(CheckoutController $c, array $p, string $body): Response
                => ok_response($c->checkout($body), 'Pedido creado', 201),
            true);
    }
}
