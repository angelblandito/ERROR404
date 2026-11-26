<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de owner. Traduccion de controllers/<entidad>_controller.py del profe. */
final class OwnerController extends BaseController
{
    public const MODEL_CLASS = OwnerModel::class;
    public const SCHEMA = OwnerSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'owner', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'owner');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'owner', $data, $action);
        Policy::hash_password($data);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'owner', $action, $data);
    }
}
