<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de CustomerOrder. Traduccion de schemas/<entidad>_schema.py del profe. */
final class CustomerOrderSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_store',
            'id_customer',
            'id_employee',
            'id_promotion',
            'order_date',
            'status',
            'channel',
            'subtotal',
            'discount',
            'total',
            'delivery_type',
            'id_address',
            'shipping_cost',
            'priority_shipping',
            'gift_cost',
            'notes'
        ]);
        validate_integer($data, 'id_store', null, null, true, $partial);
        validate_integer($data, 'id_customer', null, null, true, $partial);
        validate_integer($data, 'id_employee', null, null, false, $partial);
        validate_integer($data, 'id_promotion', null, null, false, $partial);
        validate_datetime($data, 'order_date', false, $partial);
        validate_enum($data, 'status', ['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'returned'], $partial);
        validate_enum($data, 'channel', ['in_store', 'online', 'whatsapp'], $partial);
        validate_number($data, 'subtotal', 0.00, 99999999.99, true, $partial);
        validate_number($data, 'discount', 0.00, 99999999.99, false, $partial);
        validate_number($data, 'total', 0.00, 99999999.99, true, $partial);
        if (isset($data['delivery_type'])) { validate_enum($data, 'delivery_type', ['pickup', 'shipping'], $partial); }
        validate_integer($data, 'id_address', null, null, false, $partial);
        validate_number($data, 'shipping_cost', 0.00, 999999.99, false, $partial);
        validate_boolean($data, 'priority_shipping', false, $partial);
        validate_number($data, 'gift_cost', null, 9999.99, false, $partial);
        validate_string($data, 'notes', 255, false, $partial);
    }
}
