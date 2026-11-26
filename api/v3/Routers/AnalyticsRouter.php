<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\AnalyticsController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: visits, searches and app installs (public) and their reports (Administrador).
 * Layer: routers. It only registers routes; the logic lives in AnalyticsController and the services.
 */
final class AnalyticsRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'POST',
            '/analytics/ping',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->ping($body), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'POST',
            '/analytics/leave',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->leave($body), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'POST',
            '/analytics/search',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->search($body), 201, headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'POST',
            '/analytics/install',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->install($body), 201, headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/admin/analytics',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->report(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/analytics/online',
            AnalyticsController::class,
            static fn (AnalyticsController $c, array $p, string $body): Response => Response::ok($c->online(), headers: $c->responseHeaders),
        );
    }
}
