<?php
declare(strict_types=1);

namespace Apicore\Core;

use ValueError;

/**
 * Security: password hashing and manual JWT (HS256) signing/verification.
 *
 * Layer: core (cross-cutting). Pure crypto and payload handling, no database
 * access here (token persistence lives in Services\TokenService).
 *
 * Passwords are stored with bcrypt, the format saved in customer_credential
 * and owner. PBKDF2 hashes
 * ("algorithm$iterations$salt$hash") are still verified.
 */
final class Security
{
    public const HASH_ITERATIONS = 100000;

    // --- Password hashing ---

    /** Return a bcrypt hash ("$2y$10$..."): self-contained, the salt is random. */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /** True when a stored bcrypt hash should be recalculated with the current cost. */
    public static function needsRehash(string $storedHash): bool
    {
        return self::isBcrypt($storedHash) && password_needs_rehash($storedHash, PASSWORD_BCRYPT);
    }

    private static function isBcrypt(string $storedHash): bool
    {
        return (bool) preg_match('/^\$2[aby]\$\d{2}\$/', $storedHash);
    }

    /**
     * Check a password against a stored hash in constant time.
     *
     * Accepts bcrypt (the hashes of the store), "algorithm$iterations$salt$hash"
     * and the legacy "salt:hash" format (PBKDF2-SHA256, 100000 iterations).
     */
    public static function verifyPassword(string $password, string $storedHash): bool
    {
        if (self::isBcrypt($storedHash)) {
            return password_verify($password, $storedHash);
        }
        if (str_contains($storedHash, '$')) {
            $parts = explode('$', $storedHash);
            if (count($parts) !== 4) {
                return false;
            }
            [, $iterations, $salt, $expected] = $parts;
        } else {
            $parts = explode(':', $storedHash);
            if (count($parts) !== 2) {
                return false;
            }
            [$salt, $expected] = $parts;
            $iterations = (string) self::HASH_ITERATIONS;
        }
        if (!ctype_xdigit($salt) || !ctype_digit($iterations)) {
            return false;
        }
        $digest = hash_pbkdf2('sha256', $password, hex2bin($salt), (int) $iterations, 0, false);
        return hash_equals($expected, $digest);
    }

    // --- Base64 URL encoding (JWT) ---

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = $data . str_repeat('=', (4 - strlen($data) % 4) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false) {
            throw new UnauthorizedError('Invalid token');
        }
        return $decoded;
    }

    // --- Tokens ---

    /** HMAC-SHA256 signature of "header.payload". */
    private static function sign(string $signingInput): string
    {
        $secret = Settings::instance()->JWT_SECRET;
        return self::base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
    }

    /** SHA-256 of the raw token: this is what is stored and looked up in the DB. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Claims of a new token. lifetimeMinutes is ignored when persistent=true
     * (no `exp` claim); minRemainingMinutes is only a claim for the client.
     *
     * @return array<string, mixed>
     */
    public static function generatePayload(
        int $userId,
        string $role,
        int $lifetimeMinutes,
        int $minRemainingMinutes,
        bool $persistent = false,
    ): array {
        $now = time();
        $payload = [
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'jti' => bin2hex(random_bytes(16)),
            'min_remaining_minutes' => $minRemainingMinutes,
        ];
        if (!$persistent) {
            $payload['exp'] = $now + $lifetimeMinutes * 60;
        }
        return $payload;
    }

    /**
     * Sign "header.payload" (HS256) and return the compact JWT.
     *
     * @param array<string, mixed> $payload
     */
    public static function encodeToken(array $payload): string
    {
        $header = ['alg' => Settings::instance()->JWT_ALGORITHM, 'typ' => 'JWT'];
        $signingInput = self::base64UrlEncode(self::jsonEncode($header))
            . '.' . self::base64UrlEncode(self::jsonEncode($payload));
        return $signingInput . '.' . self::sign($signingInput);
    }

    /**
     * Validate structure, algorithm, signature and expiration (no DB check).
     *
     * Returns the payload. Throws UnauthorizedError on any failure.
     *
     * @return array<string, mixed>
     */
    public static function verifyToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnauthorizedError('Invalid token');
        }
        [$headerB64, $payloadB64, $signature] = $parts;
        $header = json_decode(self::base64UrlDecode($headerB64), true);
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($header) || !is_array($payload)) {
            throw new UnauthorizedError('Invalid token');
        }
        if (($header['alg'] ?? null) !== Settings::instance()->JWT_ALGORITHM) {
            throw new UnauthorizedError('Invalid token');
        }
        if (!hash_equals(self::sign($headerB64 . '.' . $payloadB64), $signature)) {
            throw new UnauthorizedError('Invalid token');
        }
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new UnauthorizedError('Token expired');
        }
        return $payload;
    }

    /**
     * JSON without spaces between separators.
     *
     * @param array<string, mixed> $value
     */
    private static function jsonEncode(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new ValueError('Unable to encode token payload');
        }
        return $encoded;
    }
}
