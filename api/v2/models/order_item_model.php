<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla order_item. Traduccion de models/<entidad>.py del profe. */
final class OrderItemModel extends BaseModel
{
    public const TABLE_NAME = 'order_item';
    public const ID_COLUMN = 'id_item';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_item',
        'id_order',
        'id_variant',
        'quantity',
        'unit_price',
        'updated_at'
    ];
}
