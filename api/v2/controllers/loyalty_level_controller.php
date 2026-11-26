<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de loyalty_level. Traduccion de controllers/<entidad>_controller.py del profe. */
final class LoyaltyLevelController extends BaseController
{
    public const MODEL_CLASS = LoyaltyLevelModel::class;
    public const SCHEMA = LoyaltyLevelSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'loyalty_level', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'loyalty_level');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'loyalty_level', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'loyalty_level', $action, $data);
    }
}
