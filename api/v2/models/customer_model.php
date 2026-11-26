<?php
// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.

/** Acceso a la tabla customer. Traduccion de models/<entidad>.py del profe. */
final class CustomerModel extends BaseModel
{
    public const TABLE_NAME = 'customer';
    public const ID_COLUMN = 'id_customer';
    public const HAS_SOFT_DELETE = true;
    public const HAS_UPDATED_AT = true;
    public const SOURCE_NODE = 'php_api';
    public const READABLE = [
        'id_customer',
        'id_loyalty_level',
        'id_account_tier',
        'id_store',
        'id_address',
        'first_name',
        'last_name_paternal',
        'last_name_maternal',
        'birth_date',
        'sex',
        'phone',
        'email',
        'accepts_marketing',
        'customer_since',
        'updated_at'
    ];
}
