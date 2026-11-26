<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla postal_code. Traduccion de models/<entidad>.py del profe. */
final class PostalCodeModel extends BaseModel
{
    public const TABLE_NAME = 'postal_code';
    public const ID_COLUMN = 'id_postal_code';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [
        'id_postal_code',
        'zip_code',
        'settlement',
        'settlement_type',
        'municipality',
        'city',
        'id_state'
    ];
}
