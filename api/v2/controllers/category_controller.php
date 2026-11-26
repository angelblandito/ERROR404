<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de category. Traduccion de controllers/<entidad>_controller.py del profe. */
final class CategoryController extends BaseController
{
    public const MODEL_CLASS = CategoryModel::class;
    public const SCHEMA = CategorySchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'category', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'category');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'category', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'category', $action, $data);
    }
}
