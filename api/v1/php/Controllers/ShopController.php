<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\AppError;
use Apicore\Services\AccountService;
use Apicore\Services\SeasonThemeService;
use Apicore\Services\StoreService;

/**
 * Online store: catalog, reviews, announcements, coupons, seasons and postal codes.
 *
 * Layer: controllers. Reads the parameters, calls the services and returns data.
 */
final class ShopController extends ActionController
{
    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return StoreService::catalog();
    }

    /** @return array<string, mixed> */
    public function reviews(int $productId): array
    {
        return StoreService::reviews($productId);
    }

    /** @return array<int|string, mixed> */
    public function alsoBought(int $productId): array
    {
        return StoreService::alsoBought($productId);
    }

    /** @return array<string, mixed> */
    public function createReview(string $body): array
    {
        return StoreService::createReview($this->authUser, $this->body($body));
    }

    /** @return array<int|string, mixed> */
    public function announcements(): array
    {
        return AccountService::announcements($this->authUser);
    }

    /** @return array<string, mixed> */
    public function coupon(string $code): array
    {
        return StoreService::coupon(rawurldecode($code));
    }

    /** @return array<string, mixed> */
    public function postalCode(string $zip): array
    {
        if (!preg_match('/^\d{5}$/', $zip)) {
            throw new AppError('Not found', 404);
        }
        return StoreService::postalCode($zip);
    }

    /** @return array<string, mixed> */
    public function currentTheme(): array
    {
        return SeasonThemeService::current($this->queryText('date'));
    }
}
