<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de account_tier. Traduccion de controllers/<entidad>_controller.py del profe. */
final class AccountTierController extends BaseController
{
    public const MODEL_CLASS = AccountTierModel::class;
    public const SCHEMA = AccountTierSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'account_tier', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'account_tier');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'account_tier', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'account_tier', $action, $data);
    }
}
