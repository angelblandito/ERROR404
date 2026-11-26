<?php
/**
 * Compra en linea: arma el pedido con dbo.sp_crear_pedido en el nodo central.
 * Layer: controllers.
 */
final class CheckoutController extends ApiController
{
    public function checkout(string $body): array
    {
        return checkout_create($this->auth_user, $this->body($body));
    }
}
