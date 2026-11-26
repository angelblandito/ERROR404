<?php
/**
 * Cuentas de cliente: registro, inicio y cierre de sesion, perfil y recuperacion de contrasena.
 * Layer: controllers. El token lo valida el router; aqui solo se usa para cerrar la sesion.
 */
final class AuthController extends ApiController
{
    public function register(string $body): array
    {
        return store_register($this->body($body));
    }

    public function login(string $body): array
    {
        return store_login($this->body($body));
    }

    public function logout(): array
    {
        return store_logout($this->auth_token);
    }

    public function me(): array
    {
        return store_profile(store_db(), store_require_customer($this->auth_user));
    }

    public function forgot(string $body): array
    {
        return recovery_forgot($this->body($body));
    }

    public function reset(string $body): array
    {
        return recovery_reset($this->body($body));
    }
}
