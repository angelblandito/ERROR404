<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Controller de support_ticket. Traduccion de controllers/<entidad>_controller.py del profe. */
final class SupportTicketController extends BaseController
{
    public const MODEL_CLASS = SupportTicketModel::class;
    public const SCHEMA = SupportTicketSchema::class;

    protected function allow(string $action): bool
    {
        return Policy::allowed($this->auth_user, 'support_ticket', $action);
    }

    protected function scope(): ?array
    {
        return Policy::scope($this->auth_user, 'support_ticket');
    }

    protected function prepare(array &$data, string $action): void
    {
        Policy::prepare($this->auth_user, 'support_ticket', $data, $action);
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
        Policy::authorize($this->auth_user, 'support_ticket', $action, $data);
    }
}
