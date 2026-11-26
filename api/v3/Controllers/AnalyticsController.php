<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Services\AnalyticsService;
use Apicore\Services\StoreService;

/**
 * Visits, searches and app installs. Recording is public (the page sends it);
 * the reports are for the administrator only.
 *
 * Layer: controllers.
 */
final class AnalyticsController extends ActionController
{
    /** @return array<string, mixed> */
    public function ping(string $body): array
    {
        return AnalyticsService::ping(StoreService::db(), $this->body($body), $this->authUser);
    }

    /** @return array<string, mixed> */
    public function leave(string $body): array
    {
        return AnalyticsService::leave(StoreService::db(), $this->body($body));
    }

    /** @return array<string, mixed> */
    public function search(string $body): array
    {
        return AnalyticsService::search(StoreService::db(), $this->body($body));
    }

    /** @return array<string, mixed> */
    public function install(string $body): array
    {
        return AnalyticsService::install(StoreService::db(), $this->body($body));
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        StoreService::requireAdmin($this->authUser);
        return AnalyticsService::report(StoreService::db());
    }

    /** @return array<string, mixed> */
    public function online(): array
    {
        StoreService::requireAdmin($this->authUser);
        return AnalyticsService::online(StoreService::db());
    }
}
