<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de Competitor. Traduccion de schemas/<entidad>_schema.py del profe. */
final class CompetitorSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'name',
            'website'
        ]);
        validate_string($data, 'name', 40, true, $partial);
        validate_string($data, 'website', 100, false, $partial);
    }
}
