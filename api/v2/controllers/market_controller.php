<?php
/**
 * Comparador de mercado (cuentas Prioritario y Administrador).
 * Layer: controllers.
 */
final class MarketController extends ApiController
{
    public function compare(): array
    {
        return market_compare(store_db(), market_require_member($this->auth_user));
    }
}
