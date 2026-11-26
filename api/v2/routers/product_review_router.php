<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Rutas de product_review. Traduccion de routers/<entidad>_router.py del profe. */
final class ProductReviewRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_crud_routes('/product_review', ProductReviewController::class, 'Product review', false);
    }
}
