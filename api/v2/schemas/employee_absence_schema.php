<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de EmployeeAbsence. Traduccion de schemas/<entidad>_schema.py del profe. */
final class EmployeeAbsenceSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_employee',
            'id_absence_type',
            'absence_date'
        ]);
        validate_integer($data, 'id_employee', null, null, true, $partial);
        validate_integer($data, 'id_absence_type', 0, 255, true, $partial);
        validate_date($data, 'absence_date', true, $partial);
    }
}
