<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de product_review. Traduccion de controllers/<entidad>_controller.py del profe. */
final class ProductReviewController extends BaseController
{
    public const MODEL_CLASS = ProductReviewModel::class;
    public const SCHEMA = ProductReviewSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'product_review', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'product_review');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'product_review', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'product_review', $action, $data);
    }
}
