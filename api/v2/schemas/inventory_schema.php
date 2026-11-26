<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Inventory. Traduccion de schemas/<entidad>_schema.py del profe. */
final class InventorySchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_store',
            'id_variant',
            'stock',
            'min_stock'
        ]);
        validate_integer($data, 'id_store', null, null, true, $partial);
        validate_integer($data, 'id_variant', null, null, true, $partial);
        validate_integer($data, 'stock', 0, null, false, $partial);
        validate_integer($data, 'min_stock', 0, null, false, $partial);
    }
}
