<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de customer_order. Traduccion de controllers/<entidad>_controller.py del profe. */
final class CustomerOrderController extends BaseController
{
    public const MODEL_CLASS = CustomerOrderModel::class;
    public const SCHEMA = CustomerOrderSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'customer_order', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'customer_order');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'customer_order', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'customer_order', $action, $data);
    }
}
