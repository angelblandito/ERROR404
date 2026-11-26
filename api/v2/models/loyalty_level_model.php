<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla loyalty_level. Traduccion de models/<entidad>.py del profe. */
final class LoyaltyLevelModel extends BaseModel
{
    public const TABLE_NAME = 'loyalty_level';
    public const ID_COLUMN = 'id_loyalty_level';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [
        'id_loyalty_level',
        'code',
        'name',
        'discount_percent',
        'min_spent'
    ];
}
