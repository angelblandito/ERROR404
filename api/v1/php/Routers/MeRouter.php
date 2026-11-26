<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\MeController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: orders and support tickets of the signed-in customer.
 * Layer: routers. It only registers routes; the logic lives in MeController and the services.
 */
final class MeRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'GET',
            '/me/orders',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->orders(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/support/tickets',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->tickets(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/support/tickets',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->createTicket($body), 201, headers: $c->responseHeaders),
        );
    }
}
