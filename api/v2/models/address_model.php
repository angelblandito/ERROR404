<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla address. Traduccion de models/<entidad>.py del profe. */
final class AddressModel extends BaseModel
{
    public const TABLE_NAME = 'address';
    public const ID_COLUMN = 'id_address';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_address',
        'street',
        'ext_number',
        'int_number',
        'neighborhood',
        'city',
        'id_state',
        'zip_code',
        'reference',
        'updated_at'
    ];
}
