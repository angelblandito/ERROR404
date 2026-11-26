<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Address. Traduccion de schemas/<entidad>_schema.py del profe. */
final class AddressSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'street',
            'ext_number',
            'int_number',
            'neighborhood',
            'city',
            'id_state',
            'zip_code',
            'reference'
        ]);
        validate_string($data, 'street', 100, true, $partial);
        validate_string($data, 'ext_number', 10, true, $partial);
        validate_string($data, 'int_number', 10, false, $partial);
        validate_string($data, 'neighborhood', 80, true, $partial);
        validate_string($data, 'city', 60, true, $partial);
        validate_integer($data, 'id_state', 0, 255, true, $partial);
        validate_string($data, 'zip_code', 5, true, $partial);
        validate_string($data, 'reference', 150, false, $partial);
    }
}
