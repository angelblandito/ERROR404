<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\CheckoutController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: online purchase.
 * The router does not require a session: the service asks for it with its own message for the page.
 * Layer: routers. It only registers routes; the logic lives in CheckoutController and the services.
 */
final class CheckoutRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'POST',
            '/orders/checkout',
            CheckoutController::class,
            static fn (CheckoutController $c, array $p, string $body): Response => Response::ok($c->checkout($body), 201, headers: $c->responseHeaders),
            public: true,
        );
    }
}
