<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de EmployeeSchedule. Traduccion de schemas/<entidad>_schema.py del profe. */
final class EmployeeScheduleSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_employee',
            'weekday',
            'start_time',
            'end_time'
        ]);
        validate_integer($data, 'id_employee', null, null, true, $partial);
        validate_enum($data, 'weekday', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $partial);
        validate_time($data, 'start_time', true, $partial);
        validate_time($data, 'end_time', true, $partial);
    }
}
