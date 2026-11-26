<?php
/**
 * Rutas: Tienda en linea: catalogo, resenas, comunicados, temporada vigente y codigos postales.
 * Layer: routers. Solo registra rutas; la logica esta en ShopController y en services.
 */
final class ShopRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('GET', '/store/catalog', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->catalog(), 'Catálogo'),
            true);

        $router->add_route('GET', '/store/products/{id}/reviews', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->reviews((int) $p['id']), 'Reseñas'),
            true);

        $router->add_route('GET', '/store/products/{id}/also-bought', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->also_bought((int) $p['id']), 'También compraron'),
            true);

        $router->add_route('POST', '/store/reviews', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->create_review($body), 'Reseña publicada', 201),
            false);

        $router->add_route('GET', '/store/announcements', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->announcements(), 'Comunicados'),
            true);

        $router->add_route('GET', '/season_theme/current', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->current_theme(), 'Temporada vigente'),
            true);

        $router->add_route('GET', '/postal-codes/{zip}', ShopController::class,
            static fn(ShopController $c, array $p, string $body): Response
                => ok_response($c->postal_code($p['zip']), 'Código postal'),
            true);
    }
}
