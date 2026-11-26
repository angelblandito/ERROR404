<?php
/** Pruebas de los schemas generados por build.php (reglas tomadas del SQL). Sin base de datos. */

function test_customer_order_accepts_valid_payload(): void
{
    CustomerOrderSchema::validate([
        'id_store' => 1, 'id_customer' => 2, 'status' => 'pending',
        'channel' => 'online', 'subtotal' => 100.0, 'total' => 100.0,
    ]);
    assert_true(true);
}

function test_customer_order_rejects_bad_status(): void
{
    assert_throws(ValueError::class, static fn() => CustomerOrderSchema::validate([
        'id_store' => 1, 'id_customer' => 2, 'status' => 'lost',
        'channel' => 'online', 'subtotal' => 100.0, 'total' => 100.0,
    ]));
}

function test_unknown_field_is_rejected(): void
{
    assert_throws(ValueError::class, static fn() => CustomerOrderSchema::validate([
        'id_store' => 1, 'id_customer' => 2, 'status' => 'pending', 'channel' => 'online',
        'subtotal' => 1.0, 'total' => 1.0, 'hacker' => 1,
    ]));
}

function test_partial_empty_payload_is_rejected(): void
{
    assert_throws(ValueError::class, static fn() => ProductSchema::validate([], true));
}

function test_decimal_above_column_limit_is_rejected(): void
{
    // base_price DECIMAL(8,2): maximo 999999.99
    assert_throws(ValueError::class, static fn() => ProductSchema::validate([
        'id_category' => 1, 'name' => 'X', 'target_gender' => 'U', 'base_price' => 1000000,
    ]));
}
