<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de employee_absence. Traduccion de controllers/<entidad>_controller.py del profe. */
final class EmployeeAbsenceController extends BaseController
{
    public const MODEL_CLASS = EmployeeAbsenceModel::class;
    public const SCHEMA = EmployeeAbsenceSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'employee_absence', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'employee_absence');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'employee_absence', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'employee_absence', $action, $data);
    }
}
