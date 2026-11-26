<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla competitor. Traduccion de models/<entidad>.py del profe. */
final class CompetitorModel extends BaseModel
{
    public const TABLE_NAME = 'competitor';
    public const ID_COLUMN = 'id_competitor';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [
        'id_competitor',
        'name',
        'website'
    ];
}
