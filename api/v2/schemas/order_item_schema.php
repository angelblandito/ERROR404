<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de OrderItem. Traduccion de schemas/<entidad>_schema.py del profe. */
final class OrderItemSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_order',
            'id_variant',
            'quantity',
            'unit_price'
        ]);
        validate_integer($data, 'id_order', null, null, true, $partial);
        validate_integer($data, 'id_variant', null, null, true, $partial);
        validate_integer($data, 'quantity', 1, null, true, $partial);
        validate_number($data, 'unit_price', 0.00, 999999.99, true, $partial);
    }
}
