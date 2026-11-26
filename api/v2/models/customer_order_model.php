<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla customer_order. Traduccion de models/<entidad>.py del profe. */
final class CustomerOrderModel extends BaseModel
{
    public const TABLE_NAME = 'customer_order';
    public const ID_COLUMN = 'id_order';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_order',
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
        'notes',
        'updated_at'
    ];
}
