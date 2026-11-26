<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla promotion. Traduccion de models/<entidad>.py del profe. */
final class PromotionModel extends BaseModel
{
    public const TABLE_NAME = 'promotion';
    public const ID_COLUMN = 'id_promotion';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_promotion',
        'code',
        'description',
        'discount_percent',
        'start_date',
        'end_date',
        'updated_at'
    ];
}
