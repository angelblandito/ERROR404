<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Customer. Traduccion de schemas/<entidad>_schema.py del profe. */
final class CustomerSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_loyalty_level',
            'id_account_tier',
            'id_store',
            'id_address',
            'first_name',
            'last_name_paternal',
            'last_name_maternal',
            'birth_date',
            'sex',
            'phone',
            'email',
            'accepts_marketing',
            'customer_since'
        ]);
        validate_integer($data, 'id_loyalty_level', 0, 255, false, $partial);
        validate_integer($data, 'id_account_tier', 0, 255, false, $partial);
        validate_integer($data, 'id_store', null, null, true, $partial);
        validate_integer($data, 'id_address', null, null, false, $partial);
        validate_string($data, 'first_name', 50, true, $partial);
        validate_string($data, 'last_name_paternal', 50, true, $partial);
        validate_string($data, 'last_name_maternal', 50, false, $partial);
        validate_date($data, 'birth_date', true, $partial);
        validate_enum($data, 'sex', ['M', 'F', 'X'], $partial);
        validate_string($data, 'phone', 15, true, $partial);
        validate_string($data, 'email', 100, true, $partial);
        validate_boolean($data, 'accepts_marketing', false, $partial);
        validate_date($data, 'customer_since', false, $partial);
    }
}
