<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Security;
use Apicore\Core\Settings;
use Apicore\Core\UnauthorizedError;
use Apicore\Models\UserToken;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Token service: lifecycle of bearer tokens (create, validate, revoke).
 *
 * Layer: services. Business rules and orchestration over the `users_tokens`
 * repository (one row per token, so a user can be logged in on many devices;
 * revoking a token means deleting its row). It never knows about HTTP: no
 * request objects, no status codes. Core\Security holds the pure crypto.
 *
 * Roles: the role of a token is the code of the customer's account type
 * (table account_tier):
 *   standard  Estándar
 *   priority  Prioritario
 *   admin     Administrador (account_tier.can_manage_store = 1)
 */
final class TokenService
{
    /** users_tokens.TokenExpiration is NOT NULL, so persistent tokens get a far-future date. */
    public const PERSISTENT_EXPIRATION = '9999-12-31 23:59:59';

    /** Every token of this API belongs to a customer account (users_tokens.UserType). */
    public const USER_TYPE = 'customer';

    /**
     * Create a signed JWT and persist its hash in `users_tokens`.
     *
     * lifetimeMinutes is ignored when persistent=true (no `exp` claim).
     * minRemainingMinutes is only a claim: the client decides when to renew.
     * An existing $tokenModel is reused (and left open); otherwise a new one is
     * created and closed here. Returns the signed token and its exact payload,
     * so the response formatter need not decode the token again.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function generateToken(
        int $userId,
        string $role,
        int $lifetimeMinutes,
        int $minRemainingMinutes,
        bool $persistent = false,
        ?UserToken $tokenModel = null,
    ): array {
        $payload = Security::generatePayload($userId, $role, $lifetimeMinutes, $minRemainingMinutes, $persistent);
        $token = Security::encodeToken($payload);

        $expiration = $persistent
            ? self::PERSISTENT_EXPIRATION
            : gmdate('Y-m-d H:i:s', (int) $payload['exp']);
        $model = $tokenModel ?? new UserToken();
        try {
            $model->create([
                'UserType' => self::USER_TYPE,
                'UserId' => $userId,
                'TokenHash' => Security::hashToken($token),
                'TokenExpiration' => $expiration,
                'TokenCreatedAt' => gmdate('Y-m-d H:i:s'),
            ]);
        } finally {
            if ($tokenModel === null) {
                $model->close();
            }
        }
        return [$token, $payload];
    }

    /**
     * Format a login response from the payload already available at generation.
     *
     * The caller always holds the payload (generateToken returns it), so the JWT
     * is never parsed a second time.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function buildTokenResponse(string $token, array $payload): array
    {
        $expiresAt = isset($payload['exp']) ? self::isoformatFromTimestamp((int) $payload['exp']) : null;
        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'persistent' => !isset($payload['exp']),
            'min_remaining_minutes' => $payload['min_remaining_minutes'],
            'role' => $payload['role'],
        ];
    }

    /**
     * Validate a token and return its payload.
     *
     * Checks: structure, signature, algorithm, expiration claim (verifyToken),
     * presence in `users_tokens` with a TokenExpiration still in the future (not
     * revoked, not expired in the database) and that the customer still exists.
     * The role claim is replaced with the current account type in the database,
     * so a customer promoted to Administrador (or demoted) gets it immediately.
     * Throws UnauthorizedError on any failure.
     *
     * The payload also carries the keys the store services read from the
     * session (id_customer, tier, can_manage, support_minutes).
     *
     * @return array<string, mixed>
     */
    public static function decodeToken(string $token): array
    {
        $payload = Security::verifyToken($token);
        $model = new UserToken();
        try {
            $state = $model->getUserState(Security::hashToken($token));
        } finally {
            $model->close();
        }
        if ($state === null) {
            throw new UnauthorizedError('Token revoked or unknown');
        }
        // The database is the source of truth for the role: a token issued before
        // a change of account type must not keep the old privileges
        $payload['sub'] = $state['id_customer'];
        $payload['role'] = $state['tier'];
        $payload['id_customer'] = $state['id_customer'];
        $payload['tier'] = $state['tier'];
        $payload['can_manage'] = $state['can_manage'];
        $payload['support_minutes'] = $state['support_minutes'];
        return $payload;
    }

    /** Delete the token row. Returns true if it existed. */
    public static function revokeToken(string $token): bool
    {
        $model = new UserToken();
        try {
            return $model->deleteByHash(Security::hashToken($token)) > 0;
        } finally {
            $model->close();
        }
    }

    /** Close every session of one customer (password reset). */
    public static function revokeAllOf(string $userType, int $userId): int
    {
        $model = new UserToken();
        try {
            return $model->deleteByUser($userType, $userId);
        } finally {
            $model->close();
        }
    }

    /** Epoch seconds -> ISO 8601 string in APP_TIMEZONE. */
    public static function isoformatFromTimestamp(int $epoch): string
    {
        $timezone = new DateTimeZone(Settings::instance()->APP_TIMEZONE);
        return (new DateTimeImmutable('@' . $epoch))->setTimezone($timezone)->format('Y-m-d\TH:i:sP');
    }
}
