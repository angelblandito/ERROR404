<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de role. Traduccion de controllers/<entidad>_controller.py del profe. */
final class RoleController extends BaseController
{
    public const MODEL_CLASS = RoleModel::class;
    public const SCHEMA = RoleSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'role', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'role');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'role', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'role', $action, $data);
    }
}
