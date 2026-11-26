<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de OrderGift. Traduccion de schemas/<entidad>_schema.py del profe. */
final class OrderGiftSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'occasion',
            'wrap_color',
            'recipient_name',
            'message'
        ]);
        validate_enum($data, 'occasion', ['birthday', 'friendship', 'christmas'], $partial);
        validate_enum($data, 'wrap_color', ['red', 'green', 'blue', 'pink', 'gold', 'silver', 'purple', 'black'], $partial);
        validate_string($data, 'recipient_name', 60, false, $partial);
        validate_string($data, 'message', 200, false, $partial);
    }
}
