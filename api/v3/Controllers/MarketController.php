<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Services\MarketService;
use Apicore\Services\StoreService;

/**
 * Market comparison (Prioritario and Administrador accounts).
 *
 * Layer: controllers.
 */
final class MarketController extends ActionController
{
    /** @return array<string, mixed> */
    public function compare(): array
    {
        return MarketService::compare(StoreService::db(), MarketService::requireMember($this->authUser));
    }
}
