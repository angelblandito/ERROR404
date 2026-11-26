<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de ProductVariant. Traduccion de schemas/<entidad>_schema.py del profe. */
final class ProductVariantSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_product',
            'id_size',
            'id_color',
            'sku',
            'barcode'
        ]);
        validate_integer($data, 'id_product', null, null, true, $partial);
        validate_integer($data, 'id_size', 0, 255, true, $partial);
        validate_integer($data, 'id_color', 0, 255, true, $partial);
        validate_string($data, 'sku', 20, true, $partial);
        validate_string($data, 'barcode', 13, false, $partial);
    }
}
