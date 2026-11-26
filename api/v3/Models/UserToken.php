<?php
declare(strict_types=1);

namespace Apicore\Models;

/**
 * UserToken model: table definition and queries specific to `users_tokens`
 * (script API_JAM/distribuida/09_tokens_y_accesos.sql).
 *
 * Layer: models. A user may own many rows (one per device/session); revoking a
 * token means deleting its row. UserId is the customer id and UserType is
 * always 'customer' (the column lets other kinds of accounts be added later
 * without changing the table).
 */
final class UserToken extends BaseModel
{
    protected const TABLE_NAME = 'users_tokens';
    protected const ID_COLUMN = 'TokenId';
    protected const COLUMNS = ['UserType', 'UserId', 'TokenHash', 'TokenExpiration', 'TokenCreatedAt'];
    protected const INTEGER_COLUMNS = ['TokenId', 'UserId'];

    /**
     * Customer and account type of a valid token hash, or null.
     *
     * Null when the token does not exist, its TokenExpiration already passed or
     * the customer was deleted, so a row edited to an earlier date stops
     * authorizing requests. The account type is read on every request, so a
     * customer promoted to Administrador (or demoted) gets it immediately
     * instead of waiting for the token to expire.
     *
     * @return array{id_customer: int, tier: string, can_manage: bool, support_minutes: int}|null
     */
    public function getUserState(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id_customer, a.code AS tier, a.can_manage_store, a.support_minutes
             FROM dbo.users_tokens AS t
             JOIN dbo.customer AS c ON c.id_customer = t.UserId AND c.deleted = 0
             JOIN dbo.account_tier AS a ON a.id_account_tier = c.id_account_tier
             WHERE t.TokenHash = ? AND t.UserType = \'customer\' AND t.TokenExpiration > SYSUTCDATETIME()'
        );
        $statement->execute([$tokenHash]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id_customer' => (int) $row['id_customer'],
            'tier' => (string) $row['tier'],
            'can_manage' => (int) $row['can_manage_store'] === 1,
            'support_minutes' => (int) $row['support_minutes'],
        ];
    }

    /** Revoke one token. */
    public function deleteByHash(string $tokenHash): int
    {
        $statement = $this->pdo->prepare('DELETE FROM dbo.users_tokens WHERE TokenHash = ?');
        $statement->execute([$tokenHash]);
        return $statement->rowCount();
    }

    /**
     * Housekeeping: delete tokens whose expiration date passed. Returns rows deleted.
     *
     * Persistent tokens hold a far-future date (year 9999), so they survive this.
     */
    public function deleteExpired(): int
    {
        $statement = $this->pdo->query('DELETE FROM dbo.users_tokens WHERE TokenExpiration < SYSUTCDATETIME()');
        return $statement === false ? 0 : $statement->rowCount();
    }

    /** Revoke every token of one customer (password change). */
    public function deleteByUser(string $userType, int|string $userId): int
    {
        $statement = $this->pdo->prepare('DELETE FROM dbo.users_tokens WHERE UserType = ? AND UserId = ?');
        $statement->execute([$userType, $userId]);
        return $statement->rowCount();
    }
}
