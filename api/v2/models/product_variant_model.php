<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla product_variant. Traduccion de models/<entidad>.py del profe. */
final class ProductVariantModel extends BaseModel
{
    public const TABLE_NAME = 'product_variant';
    public const ID_COLUMN = 'id_variant';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_variant',
        'id_product',
        'id_size',
        'id_color',
        'sku',
        'barcode',
        'updated_at'
    ];
}
