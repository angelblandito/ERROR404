<?php
/**
 * Rutas: Cuentas: registro, sesion, perfil y recuperacion de contrasena.
 * Layer: routers. Solo registra rutas; la logica esta en AuthController y en services.
 */
final class AuthRouter
{
    public static function register_routes(Router $router): void
    {
        $router->add_route('POST', '/auth/register', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->register($body), 'Cuenta creada', 201),
            true);

        $router->add_route('POST', '/auth/login', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->login($body), 'Sesión iniciada'),
            true);

        $router->add_route('POST', '/auth/logout', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->logout(), 'Sesión cerrada'),
            true);

        $router->add_route('GET', '/auth/me', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->me(), 'Perfil'),
            false);

        $router->add_route('POST', '/auth/forgot', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->forgot($body), 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.'),
            true);

        $router->add_route('POST', '/auth/reset', AuthController::class,
            static fn(AuthController $c, array $p, string $body): Response
                => ok_response($c->reset($body), 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.'),
            true);
    }
}
