<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla product. Traduccion de models/<entidad>.py del profe. */
final class ProductModel extends BaseModel
{
    public const TABLE_NAME = 'product';
    public const ID_COLUMN = 'id_product';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_product',
        'id_category',
        'name',
        'description',
        'target_gender',
        'base_price',
        'age_segment',
        'image_url',
        'outlet_percent',
        'updated_at'
    ];
}
