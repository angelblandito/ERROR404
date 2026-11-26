<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla season_theme. Traduccion de models/<entidad>.py del profe. */
final class SeasonThemeModel extends BaseModel
{
    public const TABLE_NAME = 'season_theme';
    public const ID_COLUMN = 'id_theme';
    public const ORDER_BY = '[priority] DESC, [id_theme]';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [
        'id_theme',
        'code',
        'name',
        'theme_type',
        'start_month',
        'start_day',
        'end_month',
        'end_day',
        'priority',
        'color_primary',
        'color_secondary',
        'color_accent',
        'color_accent_text',
        'hero_title',
        'hero_text',
        'effect',
        'active'
    ];
}
