<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\AdminController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Store admin panel routes (Administrador accounts only).
 * Layer: routers. It only registers routes; the logic lives in AdminController and the services.
 */
final class AdminRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'GET',
            '/admin/summary',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->summary(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/customers',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->customers(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/customers/{id}/tier',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->setTier((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/admin/customers/evaluate',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->evaluatePriority(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/orders',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->orders(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/orders/{id}/status',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->setOrderStatus((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/products/{id}',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->setPrice((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/admin/products/{id}/photo',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->savePhoto((int) $p['id'], $body), 201, headers: $c->responseHeaders),
        );
        $router->addRoute(
            'DELETE',
            '/admin/products/{id}/photo',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->deletePhoto((int) $p['id']), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/products/{id}/seasons',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->setSeasons((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/audit',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->audit(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/inventory',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->setStock($body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/tickets',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->tickets(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/tickets/{id}',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->answerTicket((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/announcements',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->announcements(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/admin/announcements',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->createAnnouncement($body), 201, headers: $c->responseHeaders),
        );
        $router->addRoute(
            'DELETE',
            '/admin/announcements/{id}',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->deleteAnnouncement((int) $p['id']), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/emails',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->emails(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'GET',
            '/admin/market',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->market(), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'POST',
            '/admin/market',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->createMarketPrice($body), 201, headers: $c->responseHeaders),
        );
        $router->addRoute(
            'PUT',
            '/admin/market/{id}',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->updateMarketPrice((int) $p['id'], $body), headers: $c->responseHeaders),
        );
        $router->addRoute(
            'DELETE',
            '/admin/market/{id}',
            AdminController::class,
            static fn (AdminController $c, array $p, string $body): Response => Response::ok($c->deleteMarketPrice((int) $p['id']), headers: $c->responseHeaders),
        );
    }
}
