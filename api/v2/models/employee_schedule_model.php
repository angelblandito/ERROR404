<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla employee_schedule. Traduccion de models/<entidad>.py del profe. */
final class EmployeeScheduleModel extends BaseModel
{
    public const TABLE_NAME = 'employee_schedule';
    public const ID_COLUMN = 'id_schedule';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_schedule',
        'id_employee',
        'weekday',
        'start_time',
        'end_time',
        'updated_at'
    ];
}
