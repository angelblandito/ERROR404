<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de postal_code. Traduccion de controllers/<entidad>_controller.py del profe. */
final class PostalCodeController extends BaseController
{
    public const MODEL_CLASS = PostalCodeModel::class;
    public const SCHEMA = PostalCodeSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'postal_code', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'postal_code');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'postal_code', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'postal_code', $action, $data);
    }
}
