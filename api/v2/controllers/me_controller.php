<?php
/**
 * Lo que el cliente con sesion ve de si mismo: sus pedidos y sus tickets de soporte.
 * Layer: controllers. El id del cliente sale del actor de la sesion, nunca del cuerpo.
 */
final class MeController extends ApiController
{
    public function orders(): array
    {
        return store_my_orders($this->auth_user);
    }

    public function tickets(): array
    {
        return account_my_tickets($this->auth_user);
    }

    public function create_ticket(string $body): array
    {
        return account_create_ticket($this->auth_user, $this->body($body));
    }
}
