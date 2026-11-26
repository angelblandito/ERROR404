<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Size. Traduccion de schemas/<entidad>_schema.py del profe. */
final class SizeSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'code',
            'name'
        ]);
        validate_string($data, 'code', 3, true, $partial);
        validate_string($data, 'name', 20, true, $partial);
    }
}
