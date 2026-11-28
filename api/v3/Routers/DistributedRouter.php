<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\DistributedController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: distributed database: nodes, fragments, replication, sync, the Azure mirror and the
 * distributed transactions (transfer, transaction log, global checkpoint). Administrador only.
 * Layer: routers. It only registers routes; the logic lives in DistributedController and the services.
 */
final class DistributedRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'GET',
            '/nodes',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->nodes(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/distributed/inventory',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->inventory(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/distributed/orders',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->orders(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/distributed/customers',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->customers(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/distributed/fragments',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->fragments(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/replication/status',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->replication(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/distributed/sync',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->sync(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/distributed/transfer',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->transfer($body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/distributed/transactions',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->transactions(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/distributed/checkpoint',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->checkpoint(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/mirror/sync',
            DistributedController::class,
            static fn (DistributedController $c, array $p, string $body): Response => Response::ok($c->mirrorSync(), headers: $c->responseHeaders),
        );
    }
}
