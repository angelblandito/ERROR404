<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla store. Traduccion de models/<entidad>.py del profe. */
final class StoreModel extends BaseModel
{
    public const TABLE_NAME = 'store';
    public const ID_COLUMN = 'id_store';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_store',
        'id_owner',
        'id_store_type',
        'id_address',
        'store_name',
        'tax_id',
        'contact_phone',
        'email',
        'whatsapp',
        'opened_at',
        'active',
        'updated_at'
    ];
}
