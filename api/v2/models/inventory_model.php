<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla inventory. Traduccion de models/<entidad>.py del profe. */
final class InventoryModel extends BaseModel
{
    public const TABLE_NAME = 'inventory';
    public const ID_COLUMN = 'id_inventory';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_inventory',
        'id_store',
        'id_variant',
        'stock',
        'min_stock',
        'updated_at'
    ];
}
