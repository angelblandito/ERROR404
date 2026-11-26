<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla account_tier. Traduccion de models/<entidad>.py del profe. */
final class AccountTierModel extends BaseModel
{
    public const TABLE_NAME = 'account_tier';
    public const ID_COLUMN = 'id_account_tier';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [
        'id_account_tier',
        'code',
        'name',
        'discount_percent',
        'free_shipping_from',
        'delivery_days',
        'max_units_per_item',
        'support_minutes',
        'can_manage_store'
    ];
}
