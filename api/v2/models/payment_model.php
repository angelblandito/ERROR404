<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla payment. Traduccion de models/<entidad>.py del profe. */
final class PaymentModel extends BaseModel
{
    public const TABLE_NAME = 'payment';
    public const ID_COLUMN = 'id_payment';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_payment',
        'id_order',
        'amount',
        'currency',
        'payment_date',
        'method',
        'external_reference',
        'status',
        'updated_at'
    ];
}
