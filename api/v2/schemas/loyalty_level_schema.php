<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de LoyaltyLevel. Traduccion de schemas/<entidad>_schema.py del profe. */
final class LoyaltyLevelSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'code',
            'name',
            'discount_percent',
            'min_spent'
        ]);
        validate_string($data, 'code', 6, true, $partial);
        validate_string($data, 'name', 10, true, $partial);
        validate_number($data, 'discount_percent', null, 99.99, true, $partial);
        validate_number($data, 'min_spent', 0.00, 99999999.99, true, $partial);
    }
}
