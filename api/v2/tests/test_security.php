<?php
/**
 * Traduccion de tests/test_security.py: hash de contrasenas y firma de JWT. Sin base de datos.
 *
 * DIFERENCIA DELIBERADA: el profe guarda contrasenas como pbkdf2_sha256$100000$salt$hash (118 caracteres).
 * En JAM las columnas password_hash son VARCHAR(100), asi que los hashes nuevos son bcrypt ($2y$, 60
 * caracteres). Los hashes pbkdf2 y "salt:hash" existentes siguen verificando (ver test_verify_legacy).
 */

function test_hash_password_is_bcrypt(): void
{
    $stored = Security::hash_password('Secret123');
    assert_true(str_starts_with($stored, '$2y$'), 'expected bcrypt hash');
    assert_same(60, strlen($stored));
}

function test_hash_password_salt_is_random(): void
{
    assert_true(Security::hash_password('Secret123') !== Security::hash_password('Secret123'));
}

function test_verify_password_roundtrip(): void
{
    $stored = Security::hash_password('Secret123');
    assert_true(Security::verify_password('Secret123', $stored));
    assert_true(!Security::verify_password('Secret124', $stored));
    assert_true(!Security::verify_password('', $stored));
}

function test_verify_password_legacy_salt_hash(): void
{
    // Filas "salt:hash" ya existentes en la base siguen validando
    $salt = str_repeat('ab', 16);
    $digest = hash_pbkdf2('sha256', 'Legacy123', hex2bin($salt), 100000);
    assert_true(Security::verify_password('Legacy123', "$salt:$digest"));
    assert_true(!Security::verify_password('Legacy124', "$salt:$digest"));
}

function test_verify_password_malformed_hash_is_false(): void
{
    foreach (['', 'garbage', 'a$b$c', 'nothex:nothex'] as $stored) {
        assert_true(!Security::verify_password('Secret123', $stored), "stored: $stored");
    }
}

function test_hash_token_is_deterministic_sha256(): void
{
    assert_same(Security::hash_token('abc'), Security::hash_token('abc'));
    assert_true(Security::hash_token('abc') !== Security::hash_token('abd'));
    assert_same(64, strlen(Security::hash_token('abc')));
}

function test_new_token_is_64_hex_and_random(): void
{
    $a = Security::new_token();
    $b = Security::new_token();
    assert_true(Security::token_format_ok($a), 'formato');
    assert_true($a !== $b, 'aleatorio');
}

function test_token_format_rejects_other_text(): void
{
    assert_true(!Security::token_format_ok('abc'), 'corto');
    assert_true(!Security::token_format_ok(str_repeat('z', 64)), 'no hexadecimal');
}
