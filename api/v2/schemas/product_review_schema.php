<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de ProductReview. Traduccion de schemas/<entidad>_schema.py del profe. */
final class ProductReviewSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_product',
            'id_customer',
            'rating',
            'title',
            'comment',
            'verified_purchase'
        ]);
        validate_integer($data, 'id_product', null, null, true, $partial);
        validate_integer($data, 'id_customer', null, null, true, $partial);
        validate_integer($data, 'rating', 1, 5, true, $partial);
        validate_string($data, 'title', 80, true, $partial);
        validate_string($data, 'comment', 1000, false, $partial);
        validate_boolean($data, 'verified_purchase', false, $partial);
    }
}
