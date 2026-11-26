<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Payment. Traduccion de schemas/<entidad>_schema.py del profe. */
final class PaymentSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_order',
            'amount',
            'currency',
            'payment_date',
            'method',
            'external_reference',
            'status'
        ]);
        validate_integer($data, 'id_order', null, null, true, $partial);
        validate_number($data, 'amount', 0.00, 99999999.99, true, $partial);
        validate_string($data, 'currency', 3, false, $partial);
        validate_datetime($data, 'payment_date', false, $partial);
        validate_enum($data, 'method', ['card', 'transfer', 'cash', 'oxxo'], $partial);
        validate_string($data, 'external_reference', 50, false, $partial);
        validate_enum($data, 'status', ['pending', 'completed', 'failed', 'refunded'], $partial);
    }
}
