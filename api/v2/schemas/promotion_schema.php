<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Promotion. Traduccion de schemas/<entidad>_schema.py del profe. */
final class PromotionSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'code',
            'description',
            'discount_percent',
            'start_date',
            'end_date'
        ]);
        validate_string($data, 'code', 20, true, $partial);
        validate_string($data, 'description', 150, false, $partial);
        validate_number($data, 'discount_percent', 0.00, 999.99, true, $partial);
        validate_date($data, 'start_date', true, $partial);
        validate_date($data, 'end_date', true, $partial);
    }
}
