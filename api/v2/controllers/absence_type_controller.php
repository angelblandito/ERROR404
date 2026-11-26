<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de absence_type. Traduccion de controllers/<entidad>_controller.py del profe. */
final class AbsenceTypeController extends BaseController
{
    public const MODEL_CLASS = AbsenceTypeModel::class;
    public const SCHEMA = AbsenceTypeSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'absence_type', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'absence_type');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'absence_type', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'absence_type', $action, $data);
    }
}
