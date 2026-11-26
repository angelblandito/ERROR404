<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\MarketController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: market comparison for Prioritario and Administrador accounts.
 * The router does not require a session: the service asks for it with its own message for the page.
 * Layer: routers. It only registers routes; the logic lives in MarketController and the services.
 */
final class MarketRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'GET',
            '/market',
            MarketController::class,
            static fn (MarketController $c, array $p, string $body): Response => Response::ok($c->compare(), headers: $c->responseHeaders),
            public: true,
        );
    }
}
