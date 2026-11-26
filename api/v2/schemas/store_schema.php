<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Store. Traduccion de schemas/<entidad>_schema.py del profe. */
final class StoreSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_owner',
            'id_store_type',
            'id_address',
            'store_name',
            'tax_id',
            'contact_phone',
            'email',
            'whatsapp',
            'opened_at',
            'active'
        ]);
        validate_integer($data, 'id_owner', null, null, true, $partial);
        validate_integer($data, 'id_store_type', 0, 255, true, $partial);
        validate_integer($data, 'id_address', null, null, false, $partial);
        validate_string($data, 'store_name', 80, true, $partial);
        validate_string($data, 'tax_id', 13, true, $partial);
        validate_string($data, 'contact_phone', 15, true, $partial);
        validate_string($data, 'email', 100, true, $partial);
        validate_string($data, 'whatsapp', 15, true, $partial);
        validate_date($data, 'opened_at', true, $partial);
        validate_boolean($data, 'active', false, $partial);
    }
}
