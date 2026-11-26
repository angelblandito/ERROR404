<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de employee. Traduccion de controllers/<entidad>_controller.py del profe. */
final class EmployeeController extends BaseController
{
    public const MODEL_CLASS = EmployeeModel::class;
    public const SCHEMA = EmployeeSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'employee', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'employee');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'employee', $data, $action);
        Policy::hash_password($data);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'employee', $action, $data);
    }
}
