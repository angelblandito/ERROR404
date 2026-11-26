<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla owner. Traduccion de models/<entidad>.py del profe. */
final class OwnerModel extends BaseModel
{
    public const TABLE_NAME = 'owner';
    public const ID_COLUMN = 'id_owner';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_owner',
        'first_name',
        'last_name_paternal',
        'last_name_maternal',
        'email',
        'phone',
        'active',
        'created_at',
        'updated_at'
    ];
}
