<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de SeasonTheme. Traduccion de schemas/<entidad>_schema.py del profe. */
final class SeasonThemeSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
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
        ]);
        validate_string($data, 'code', 20, true, $partial);
        validate_string($data, 'name', 40, true, $partial);
        validate_enum($data, 'theme_type', ['season', 'campaign'], $partial);
        validate_integer($data, 'start_month', 0, 255, true, $partial);
        validate_integer($data, 'start_day', 0, 255, true, $partial);
        validate_integer($data, 'end_month', 0, 255, true, $partial);
        validate_integer($data, 'end_day', 0, 255, true, $partial);
        validate_integer($data, 'priority', 0, 255, true, $partial);
        validate_string($data, 'color_primary', 7, true, $partial);
        validate_string($data, 'color_secondary', 7, true, $partial);
        validate_string($data, 'color_accent', 7, true, $partial);
        validate_string($data, 'color_accent_text', 7, true, $partial);
        validate_string($data, 'hero_title', 80, true, $partial);
        validate_string($data, 'hero_text', 160, true, $partial);
        if (isset($data['effect'])) { validate_enum($data, 'effect', ['none', 'snow', 'leaves', 'petals', 'sun', 'confetti', 'sparks', 'festive'], $partial); }
        validate_boolean($data, 'active', false, $partial);
    }
}
