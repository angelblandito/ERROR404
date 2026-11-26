<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Announcement. Traduccion de schemas/<entidad>_schema.py del profe. */
final class AnnouncementSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'audience',
            'title',
            'body',
            'starts_on',
            'ends_on',
            'id_author'
        ]);
        validate_enum($data, 'audience', ['all', 'priority'], $partial);
        validate_string($data, 'title', 80, true, $partial);
        validate_string($data, 'body', 600, true, $partial);
        validate_date($data, 'starts_on', true, $partial);
        validate_date($data, 'ends_on', false, $partial);
        validate_integer($data, 'id_author', null, null, false, $partial);
    }
}
