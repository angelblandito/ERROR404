<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\MeController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: orders, support tickets, progress towards Prioritario and account settings of the signed-in customer.
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
        $router->addRoute(
            'GET',
            '/me/priority',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->priority(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/me/priority/upgrade',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->upgrade(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/me/profile',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->updateProfile($body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/me/password',
            MeController::class,
            static fn (MeController $c, array $p, string $body): Response => Response::ok($c->changePassword($body), headers: $c->responseHeaders),
        );
    }
}
