<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de employee_schedule. Traduccion de controllers/<entidad>_controller.py del profe. */
final class EmployeeScheduleController extends BaseController
{
    public const MODEL_CLASS = EmployeeScheduleModel::class;
    public const SCHEMA = EmployeeScheduleSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'employee_schedule', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'employee_schedule');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'employee_schedule', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'employee_schedule', $action, $data);
    }
}
