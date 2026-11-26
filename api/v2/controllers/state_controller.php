<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de state. Traduccion de controllers/<entidad>_controller.py del profe. */
final class StateController extends BaseController
{
    public const MODEL_CLASS = StateModel::class;
    public const SCHEMA = StateSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'state', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'state');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'state', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'state', $action, $data);
    }
}
