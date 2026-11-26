<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de StoreType. Traduccion de schemas/<entidad>_schema.py del profe. */
final class StoreTypeSchema
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
        validate_string($data, 'code', 8, true, $partial);
        validate_string($data, 'name', 30, true, $partial);
    }
}
