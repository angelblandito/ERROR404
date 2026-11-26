<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de order_item. Traduccion de controllers/<entidad>_controller.py del profe. */
final class OrderItemController extends BaseController
{
    public const MODEL_CLASS = OrderItemModel::class;
    public const SCHEMA = OrderItemSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'order_item', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'order_item');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'order_item', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'order_item', $action, $data);
    }
}
