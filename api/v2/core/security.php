<?php
/**
 * Traduccion de core/security.py: hash de contrasenas y de tokens de sesion.
 * Solo biblioteca estandar. Sin acceso a la base (eso vive en services/token_service).
 *
 * Los tokens de sesion son 32 bytes aleatorios en hexadecimal; en la base solo se guarda su SHA-256
 * (tabla customer_session). Asi una sesion se puede cerrar o revocar al cambiar la contrasena.
 *
 * Diferencia con el profe: las contrasenas nuevas se guardan con bcrypt (password_hash) porque
 * las columnas password_hash de JAM_db son VARCHAR(100) y el formato pbkdf2 del profe ocupa ~118.
 * Se siguen verificando los formatos pbkdf2 (algoritmo$iteraciones$sal$hash) y salt:hash.
 */
final class Security
{
    public const HASH_ITERATIONS = 100000;

    /** Hash de referencia para igualar el tiempo cuando el correo no existe. */
    private static ?string $dummy_hash = null;

    public static function hash_password(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /** Compara en tiempo constante. Acepta bcrypt, pbkdf2 del profe y salt:hash legado. */
    public static function verify_password(string $password, ?string $stored_hash): bool
    {
        if ($stored_hash === null || $stored_hash === '') {
            // Mismo tiempo que una verificacion real: no revela si el correo existe
            self::$dummy_hash ??= password_hash('timing-equalizer', PASSWORD_BCRYPT);
            password_verify($password, self::$dummy_hash);
            return false;
        }
        if (str_starts_with($stored_hash, '$2y$') || str_starts_with($stored_hash, '$2b$')) {
            return password_verify($password, $stored_hash);
        }
        if (str_contains($stored_hash, '$')) {
            $parts = explode('$', $stored_hash);
            if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
                return false;
            }
            [, $iterations, $salt, $expected] = $parts;
        } else {
            $parts = explode(':', $stored_hash);
            if (count($parts) !== 2) {
                return false;
            }
            [$salt, $expected] = $parts;
            $iterations = self::HASH_ITERATIONS;
        }
        if (!ctype_xdigit($salt) || strlen($salt) % 2 !== 0 || !ctype_digit((string) $iterations) || (int) $iterations < 1) {
            return false;
        }
        $digest = hash_pbkdf2('sha256', $password, hex2bin($salt), (int) $iterations);
        return hash_equals($digest, strtolower($expected));
    }

    /** SHA-256 del token: es lo que se guarda y se busca en la base. */
    public static function hash_token(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Token de sesion nuevo: 64 caracteres hexadecimales. */
    public static function new_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** true si el texto tiene la forma de un token de sesion. */
    public static function token_format_ok(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', $token);
    }
}
