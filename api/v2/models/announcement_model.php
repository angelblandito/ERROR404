<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla announcement. Traduccion de models/<entidad>.py del profe. */
final class AnnouncementModel extends BaseModel
{
    public const TABLE_NAME = 'announcement';
    public const ID_COLUMN = 'id_announcement';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_announcement',
        'audience',
        'title',
        'body',
        'starts_on',
        'ends_on',
        'id_author',
        'created_at',
        'updated_at'
    ];
}
