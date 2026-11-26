<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Owner. Traduccion de schemas/<entidad>_schema.py del profe. */
final class OwnerSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'first_name',
            'last_name_paternal',
            'last_name_maternal',
            'email',
            'phone',
            'active',
            'password'
        ]);
        validate_string($data, 'first_name', 50, true, $partial);
        validate_string($data, 'last_name_paternal', 50, true, $partial);
        validate_string($data, 'last_name_maternal', 50, false, $partial);
        validate_string($data, 'email', 100, true, $partial);
        validate_string($data, 'phone', 15, true, $partial);
        validate_boolean($data, 'active', false, $partial);
        validate_string($data, 'password', 255, true, $partial);
    }
}
