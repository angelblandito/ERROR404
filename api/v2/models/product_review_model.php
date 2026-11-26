<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla product_review. Traduccion de models/<entidad>.py del profe. */
final class ProductReviewModel extends BaseModel
{
    public const TABLE_NAME = 'product_review';
    public const ID_COLUMN = 'id_review';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_review',
        'id_product',
        'id_customer',
        'rating',
        'title',
        'comment',
        'verified_purchase',
        'created_at',
        'updated_at'
    ];
}
