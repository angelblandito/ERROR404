<?php
declare(strict_types=1);

namespace Apicore\Routers;

use Apicore\Controllers\AuthController;
use Apicore\Core\Response;
use Apicore\Core\Router;

/**
 * Auth routes: login and the customer account.
 *
 * Layer: routers. One file per entity; Routers\registerAll() discovers them.
 *
 *   POST /auth/login     public   token of a customer (role = its account type)
 *   POST /auth/logout    token    revoke the token of the request
 *   POST /auth/register  public   create a customer account and sign it in
 *   GET  /auth/me        token    account of the session
 *   POST /auth/forgot    public   email a password reset link
 *   POST /auth/reset     public   set a new password with that link
 */
final class AuthRouter
{
    public static function register(Router $router): void
    {
        $router->addRoute(
            'POST',
            '/auth/login',
            AuthController::class,
            static fn (AuthController $controller, array $params, string $body): Response => new Response(
                200,
                ['status' => 'ok', 'data' => $controller->login(Router::jsonObject($body))],
            ),
            public: true,
        );

        $router->addRoute(
            'POST',
            '/auth/logout',
            AuthController::class,
            static function (AuthController $controller): Response {
                $controller->logout();
                return new Response(200, ['status' => 'ok', 'message' => 'Token revoked']);
            },
        );

        $router->addRoute(
            'POST',
            '/auth/register',
            AuthController::class,
            static fn (AuthController $controller, array $params, string $body): Response => new Response(
                201,
                ['status' => 'ok', 'data' => $controller->register(Router::jsonObject($body))],
            ),
            public: true,
        );

        $router->addRoute(
            'GET',
            '/auth/me',
            AuthController::class,
            static fn (AuthController $controller): Response => new Response(
                200,
                ['status' => 'ok', 'data' => $controller->me()],
            ),
        );

        $router->addRoute(
            'POST',
            '/auth/forgot',
            AuthController::class,
            static fn (AuthController $controller, array $params, string $body): Response => new Response(
                200,
                ['status' => 'ok', 'data' => $controller->forgot(Router::jsonObject($body))],
            ),
            public: true,
        );

        $router->addRoute(
            'POST',
            '/auth/reset',
            AuthController::class,
            static fn (AuthController $controller, array $params, string $body): Response => new Response(
                200,
                ['status' => 'ok', 'data' => $controller->reset(Router::jsonObject($body))],
            ),
            public: true,
        );
    }
}
