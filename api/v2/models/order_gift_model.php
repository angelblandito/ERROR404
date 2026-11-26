<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla order_gift. Traduccion de models/<entidad>.py del profe. */
final class OrderGiftModel extends BaseModel
{
    public const TABLE_NAME = 'order_gift';
    public const ID_COLUMN = 'id_order';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_order',
        'occasion',
        'wrap_color',
        'recipient_name',
        'message',
        'created_at'
    ];
}
