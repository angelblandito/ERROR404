<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla employee. Traduccion de models/<entidad>.py del profe. */
final class EmployeeModel extends BaseModel
{
    public const TABLE_NAME = 'employee';
    public const ID_COLUMN = 'id_employee';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_employee',
        'id_store',
        'id_role',
        'first_name',
        'last_name_paternal',
        'last_name_maternal',
        'phone',
        'phone_verified',
        'email',
        'must_change_password',
        'hire_date',
        'updated_at'
    ];
}
