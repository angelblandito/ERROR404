<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Reglas de entrada de SupportTicket. Traduccion de schemas/<entidad>_schema.py del profe. */
final class SupportTicketSchema
{
    public static function validate(array $data, bool $partial = false): void
    {
        if ($partial && $data === []) {
            throw new ValueError('No fields to update');
        }
        reject_unknown_fields($data, [
            'id_customer',
            'id_order',
            'subject',
            'message',
            'priority',
            'status',
            'response',
            'id_responder',
            'respond_by',
            'answered_at'
        ]);
        validate_integer($data, 'id_customer', null, null, true, $partial);
        validate_integer($data, 'id_order', null, null, false, $partial);
        validate_string($data, 'subject', 100, true, $partial);
        validate_string($data, 'message', 1000, true, $partial);
        validate_enum($data, 'priority', ['normal', 'high'], $partial);
        if (isset($data['status'])) { validate_enum($data, 'status', ['open', 'answered', 'closed'], $partial); }
        validate_string($data, 'response', 1000, false, $partial);
        validate_integer($data, 'id_responder', null, null, false, $partial);
        validate_datetime($data, 'respond_by', true, $partial);
        validate_datetime($data, 'answered_at', false, $partial);
    }
}
