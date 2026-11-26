<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de inventory. Traduccion de controllers/<entidad>_controller.py del profe. */
final class InventoryController extends BaseController
{
    public const MODEL_CLASS = InventoryModel::class;
    public const SCHEMA = InventorySchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'inventory', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'inventory');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'inventory', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'inventory', $action, $data);
    }
}
