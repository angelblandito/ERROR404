<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Services\AccountService;
use Apicore\Services\ProfileService;
use Apicore\Services\StoreService;

/**
 * What the customer of the session sees about itself: orders, support tickets,
 * progress towards Prioritario and the settings of its account.
 *
 * Layer: controllers. The customer id comes from the session, never from the body.
 */
final class MeController extends ActionController
{
    /** @return array<int|string, mixed> */
    public function orders(): array
    {
        return StoreService::myOrders($this->authUser);
    }

    /** @return array<int|string, mixed> */
    public function tickets(): array
    {
        return AccountService::myTickets($this->authUser);
    }

    /** @return array<string, mixed> */
    public function createTicket(string $body): array
    {
        return AccountService::createTicket($this->authUser, $this->body($body));
    }

    /** @return array<string, mixed> */
    public function priority(): array
    {
        return AccountService::priorityStatus($this->authUser);
    }

    /** @return array<string, mixed> */
    public function upgrade(): array
    {
        return AccountService::upgradeToPriority($this->authUser);
    }

    /** @return array<string, mixed> */
    public function updateProfile(string $body): array
    {
        return ProfileService::update($this->authUser, $this->body($body));
    }

    /** @return array<string, bool> */
    public function changePassword(string $body): array
    {
        return ProfileService::changePassword($this->authUser, $this->body($body));
    }
}
