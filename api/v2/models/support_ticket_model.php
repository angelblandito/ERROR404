<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla support_ticket. Traduccion de models/<entidad>.py del profe. */
final class SupportTicketModel extends BaseModel
{
    public const TABLE_NAME = 'support_ticket';
    public const ID_COLUMN = 'id_ticket';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_ticket',
        'id_customer',
        'id_order',
        'subject',
        'message',
        'priority',
        'status',
        'response',
        'id_responder',
        'respond_by',
        'answered_at',
        'created_at',
        'updated_at'
    ];
}
