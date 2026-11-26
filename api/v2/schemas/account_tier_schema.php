<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de AccountTier. Traduccion de schemas/<entidad>_schema.py del profe. */
final class AccountTierSchema
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
            'free_shipping_from',
            'delivery_days',
            'max_units_per_item',
            'support_minutes',
            'can_manage_store'
        ]);
        validate_enum($data, 'code', ['standard', 'priority', 'admin'], $partial);
        validate_string($data, 'name', 20, true, $partial);
        validate_number($data, 'discount_percent', 0.00, 99.99, true, $partial);
        validate_number($data, 'free_shipping_from', 0.00, 99999999.99, true, $partial);
        validate_string($data, 'delivery_days', 30, true, $partial);
        validate_integer($data, 'max_units_per_item', 1, 999, true, $partial);
        validate_integer($data, 'support_minutes', 1, null, true, $partial);
        validate_boolean($data, 'can_manage_store', true, $partial);
    }
}
