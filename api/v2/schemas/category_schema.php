<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Category. Traduccion de schemas/<entidad>_schema.py del profe. */
final class CategorySchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'code',
            'name',
            'department'
        ]);
        validate_string($data, 'code', 11, true, $partial);
        validate_string($data, 'name', 20, true, $partial);
        validate_enum($data, 'department', ['apparel', 'footwear', 'accessories'], $partial);
    }
}
