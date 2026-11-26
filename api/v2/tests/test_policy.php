<?php
/** Reglas de acceso del CRUD generico (Policy) sin base de datos. */

function policy_actor(string $tier, bool $can_manage): array
{
    return ['actor_type' => 'customer', 'sub' => 7, 'role' => $tier, 'id_customer' => 7,
        'tier' => $tier, 'can_manage' => $can_manage, 'support_minutes' => 15];
}

function test_admin_can_do_everything(): void
{
    $admin = policy_actor('admin', true);
    foreach (['create', 'read', 'update', 'patch', 'delete'] as $action) {
        assert_true(Policy::allowed($admin, 'product', $action), "admin $action");
    }
}

function test_anonymous_only_reads_public_tables(): void
{
    assert_true(Policy::allowed(null, 'promotion', 'read'), 'promotion');
    assert_true(Policy::allowed(null, 'state', 'read'), 'state');
    assert_true(Policy::allowed(null, 'season_theme', 'read'), 'season_theme');
    assert_true(!Policy::allowed(null, 'promotion', 'create'), 'crear promocion');
    assert_true(!Policy::allowed(null, 'customer', 'read'), 'clientes');
    assert_true(!Policy::allowed(null, 'payment', 'read'), 'pagos');
}

function test_standard_and_priority_customers_do_not_use_the_crud(): void
{
    foreach (['standard', 'priority'] as $tier) {
        $actor = policy_actor($tier, false);
        assert_true(Policy::allowed($actor, 'promotion', 'read'), "$tier lee promociones");
        assert_true(!Policy::allowed($actor, 'customer', 'read'), "$tier no lee clientes");
        assert_true(!Policy::allowed($actor, 'product', 'update'), "$tier no cambia productos");
        assert_throws(ForbiddenError::class, static fn() => Policy::authorize($actor, 'product', 'update', []));
    }
}

function test_unknown_action_is_denied(): void
{
    assert_true(!Policy::allowed(policy_actor('admin', true), 'product', 'truncate'), 'accion desconocida');
}

function test_prepare_removes_server_fields(): void
{
    $data = ['name' => 'x', 'source_node' => 'a', 'updated_at' => 'b', 'deleted' => 1, 'password_hash' => 'h'];
    Policy::prepare(policy_actor('admin', true), 'product', $data, 'create');
    assert_same(['name' => 'x'], $data);
}

function test_hash_password_enforces_minimum_and_hashes(): void
{
    $short = ['password' => 'abc'];
    assert_throws(ValueError::class, static fn() => Policy::hash_password($short));
    $data = ['password' => 'Segura#2026'];
    Policy::hash_password($data);
    assert_true(!isset($data['password']), 'quita el texto');
    assert_true(Security::verify_password('Segura#2026', $data['password_hash']), 'bcrypt');
}
