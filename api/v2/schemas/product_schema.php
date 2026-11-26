<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Product. Traduccion de schemas/<entidad>_schema.py del profe. */
final class ProductSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_category',
            'name',
            'description',
            'target_gender',
            'base_price',
            'age_segment',
            'image_url',
            'outlet_percent'
        ]);
        validate_integer($data, 'id_category', 0, 255, true, $partial);
        validate_string($data, 'name', 80, true, $partial);
        validate_string($data, 'description', 255, false, $partial);
        validate_enum($data, 'target_gender', ['M', 'F', 'U'], $partial);
        validate_number($data, 'base_price', 0.00, 999999.99, true, $partial);
        if (isset($data['age_segment'])) { validate_enum($data, 'age_segment', ['youth', 'adult', 'all'], $partial); }
        validate_string($data, 'image_url', 255, false, $partial);
        validate_integer($data, 'outlet_percent', 0, 255, false, $partial);
    }
}
