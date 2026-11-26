<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de PostalCode. Traduccion de schemas/<entidad>_schema.py del profe. */
final class PostalCodeSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'zip_code',
            'settlement',
            'settlement_type',
            'municipality',
            'city',
            'id_state'
        ]);
        validate_string($data, 'zip_code', 5, true, $partial);
        validate_string($data, 'settlement', 80, true, $partial);
        validate_string($data, 'settlement_type', 30, true, $partial);
        validate_string($data, 'municipality', 60, true, $partial);
        validate_string($data, 'city', 60, true, $partial);
        validate_integer($data, 'id_state', 0, 255, true, $partial);
    }
}
