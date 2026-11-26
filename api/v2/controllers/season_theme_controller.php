<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de season_theme. Traduccion de controllers/<entidad>_controller.py del profe. */
final class SeasonThemeController extends BaseController
{
    public const MODEL_CLASS = SeasonThemeModel::class;
    public const SCHEMA = SeasonThemeSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'season_theme', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'season_theme');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'season_theme', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'season_theme', $action, $data);
    }
}
