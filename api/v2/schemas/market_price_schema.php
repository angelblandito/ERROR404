<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de MarketPrice. Traduccion de schemas/<entidad>_schema.py del profe. */
final class MarketPriceSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_product',
            'id_competitor',
            'competitor_product',
            'retailer',
            'price',
            'source_url',
            'captured_on'
        ]);
        validate_integer($data, 'id_product', null, null, true, $partial);
        validate_integer($data, 'id_competitor', 0, 255, true, $partial);
        validate_string($data, 'competitor_product', 100, true, $partial);
        validate_string($data, 'retailer', 40, true, $partial);
        validate_number($data, 'price', 1.00, 99999999.99, true, $partial);
        validate_string($data, 'source_url', 300, false, $partial);
        validate_date($data, 'captured_on', true, $partial);
    }
}
