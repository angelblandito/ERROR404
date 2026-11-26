<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de product_variant. Traduccion de controllers/<entidad>_controller.py del profe. */
final class ProductVariantController extends BaseController
{
    public const MODEL_CLASS = ProductVariantModel::class;
    public const SCHEMA = ProductVariantSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'product_variant', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'product_variant');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'product_variant', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'product_variant', $action, $data);
    }
}
