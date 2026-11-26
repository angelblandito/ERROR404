<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\ShopController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Routes: online store: catalog, reviews, announcements, coupons, current season and postal codes.
 * Layer: routers. It only registers routes; the logic lives in ShopController and the services.
 */
final class ShopRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'GET',
            '/store/catalog',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::cached($c->catalog(), $c->headers),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/store/products/{id}/reviews',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->reviews((int) $p['id']), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/store/products/{id}/also-bought',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->alsoBought((int) $p['id']), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'POST',
            '/store/reviews',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->createReview($body), 201, headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/store/announcements',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->announcements(), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/store/coupons/{code}',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->coupon($p['code']), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/season_theme/current',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->currentTheme(), headers: $c->responseHeaders),
            public: true,
        );
        $router->addRoute(
            'GET',
            '/postal-codes/{zip}',
            ShopController::class,
            static fn (ShopController $c, array $p, string $body): Response => Response::ok($c->postalCode($p['zip']), headers: $c->responseHeaders),
            public: true,
        );
    }
}
