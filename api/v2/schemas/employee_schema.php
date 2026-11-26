<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Employee. Traduccion de schemas/<entidad>_schema.py del profe. */
final class EmployeeSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
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
            'password'
        ]);
        validate_integer($data, 'id_store', null, null, true, $partial);
        validate_integer($data, 'id_role', 0, 255, true, $partial);
        validate_string($data, 'first_name', 50, true, $partial);
        validate_string($data, 'last_name_paternal', 50, true, $partial);
        validate_string($data, 'last_name_maternal', 50, false, $partial);
        validate_string($data, 'phone', 15, true, $partial);
        validate_boolean($data, 'phone_verified', false, $partial);
        validate_string($data, 'email', 100, true, $partial);
        validate_boolean($data, 'must_change_password', false, $partial);
        validate_date($data, 'hire_date', true, $partial);
        validate_string($data, 'password', 255, true, $partial);
    }
}
