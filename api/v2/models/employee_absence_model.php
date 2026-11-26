<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla employee_absence. Traduccion de models/<entidad>.py del profe. */
final class EmployeeAbsenceModel extends BaseModel
{
    public const TABLE_NAME = 'employee_absence';
    public const ID_COLUMN = 'id_absence';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_absence',
        'id_employee',
        'id_absence_type',
        'absence_date',
        'updated_at'
    ];
}
