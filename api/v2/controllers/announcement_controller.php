<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de announcement. Traduccion de controllers/<entidad>_controller.py del profe. */
final class AnnouncementController extends BaseController
{
    public const MODEL_CLASS = AnnouncementModel::class;
    public const SCHEMA = AnnouncementSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'announcement', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'announcement');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'announcement', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'announcement', $action, $data);
    }
}
