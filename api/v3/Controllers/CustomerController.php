<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\ForbiddenError;
use Apicore\Models\AccountTier;
use Apicore\Models\Customer;
use Apicore\Schemas\CustomerSchema;

/**
 * Customer controller: the CRUD of `customer` with the rules about account types.
 *
 * Layer: controllers. Written by hand (build.php leaves it alone) because the
 * generic rules are not enough here:
 *
 *   - Nobody gets the Administrador type through the API: neither on create nor
 *     on update. The admin panel follows the same rule (it only switches
 *     between Estándar and Prioritario).
 *   - An Administrador account cannot be modified or deleted through the API,
 *     so one administrator cannot demote or remove another one (or itself).
 *
 * Both rules live in the database data, not in a fixed id: the forbidden type
 * is any account_tier with can_manage_store = 1.
 */
final class CustomerController extends JamController
{
    protected const MODEL_CLASS = Customer::class;
    protected const SCHEMA = CustomerSchema::class;

    /** True when the account type gives access to the admin panel. */
    private function isAdminTier(int|string|null $tierId): bool
    {
        if ($tierId === null) {
            return false;
        }
        $tiers = new AccountTier();
        try {
            $tier = $tiers->getById($tierId);
        } finally {
            $tiers->close();
        }
        return $tier !== null && $tier['can_manage_store'] === true;
    }

    /** Runs before PUT, PATCH and DELETE. */
    public function authorize(int|string $recordId, string $action, ?array $data = null): void
    {
        parent::authorize($recordId, $action, $data);
        $target = $this->model->getById($recordId);
        if ($target !== null && $this->isAdminTier($target['id_account_tier'] ?? null)) {
            throw new ForbiddenError('Las cuentas de administrador no se modifican desde la API.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, int|string|null $recordId = null, bool $partial = false): array
    {
        $data = parent::prepare($data, $recordId, $partial);
        if (array_key_exists('id_account_tier', $data) && $this->isAdminTier($data['id_account_tier'])) {
            throw new ForbiddenError('El tipo de cuenta Administrador no se asigna desde la API.');
        }
        return $data;
    }
}
