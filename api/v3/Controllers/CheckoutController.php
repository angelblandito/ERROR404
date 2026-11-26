<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Services\CheckoutService;

/**
 * Online purchase: builds the order with dbo.sp_crear_pedido on the central node.
 *
 * Layer: controllers. The route is public: a guest may buy without an account,
 * and a session (when sent) adds the benefits of its account type.
 */
final class CheckoutController extends ActionController
{
    /** @return array<string, mixed> */
    public function checkout(string $body): array
    {
        return CheckoutService::create($this->authUser, $this->body($body));
    }
}
