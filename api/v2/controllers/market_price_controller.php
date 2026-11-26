<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de market_price. Traduccion de controllers/<entidad>_controller.py del profe. */
final class MarketPriceController extends BaseController
{
    public const MODEL_CLASS = MarketPriceModel::class;
    public const SCHEMA = MarketPriceSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'market_price', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'market_price');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'market_price', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'market_price', $action, $data);
    }
}
