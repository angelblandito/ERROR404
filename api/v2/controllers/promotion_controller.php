<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de promotion. Traduccion de controllers/<entidad>_controller.py del profe. */
final class PromotionController extends BaseController
{
    public const MODEL_CLASS = PromotionModel::class;
    public const SCHEMA = PromotionSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'promotion', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'promotion');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'promotion', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'promotion', $action, $data);
    }
}
