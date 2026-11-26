<?php
declare(strict_types=1);

namespace Apicore\Models;

use PDO;

/**
 * CustomerCredential model: password and lock state of a customer account
 * (`customer_credential`, one row per customer).
 *
 * Layer: models. The table has no CRUD routes: it is only touched by sign up,
 * sign in and password recovery. findByEmail() returns the hash because login
 * needs to verify it; nothing else ever returns it.
 */
final class CustomerCredential extends BaseModel
{
    protected const TABLE_NAME = 'customer_credential';
    protected const ID_COLUMN = 'id_customer';
    protected const COLUMNS = ['id_customer', 'password_hash', 'failed_attempts', 'terms_accepted_at', 'locked_until'];
    protected const HIDDEN_COLUMNS = ['password_hash'];
    protected const ID_IS_IDENTITY = false;
    protected const INTEGER_COLUMNS = ['id_customer', 'failed_attempts'];

    /**
     * Customer that may sign in with $email: id, hash and whether the account is
     * locked right now. Null when the email has no active account.
     *
     * @return array{id_customer: int, password_hash: string, is_locked: bool}|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id_customer, cr.password_hash,
                    CASE WHEN cr.locked_until > SYSUTCDATETIME() THEN 1 ELSE 0 END AS is_locked
             FROM dbo.customer AS c
             JOIN dbo.customer_credential AS cr ON cr.id_customer = c.id_customer
             WHERE c.email = ? AND c.deleted = 0'
        );
        $statement->execute([$email]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id_customer' => (int) $row['id_customer'],
            'password_hash' => (string) $row['password_hash'],
            'is_locked' => (int) $row['is_locked'] === 1,
        ];
    }

    /**
     * Wrong password: count it, and lock the whole account after $maxAttempts
     * failures (the counter starts again after the lock).
     */
    public function registerFailure(int $customerId, int $maxAttempts, int $lockMinutes): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE dbo.customer_credential
             SET failed_attempts = CASE WHEN failed_attempts + 1 >= :max_attempts THEN 0 ELSE failed_attempts + 1 END,
                 locked_until = CASE WHEN failed_attempts + 1 >= :max_attempts_lock
                                     THEN DATEADD(MINUTE, :lock_minutes, SYSUTCDATETIME()) ELSE locked_until END,
                 updated_at = SYSUTCDATETIME()
             WHERE id_customer = :id'
        );
        $statement->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
        $statement->bindValue(':max_attempts_lock', $maxAttempts, PDO::PARAM_INT);
        $statement->bindValue(':lock_minutes', $lockMinutes, PDO::PARAM_INT);
        $statement->bindValue(':id', $customerId, PDO::PARAM_INT);
        $statement->execute();
    }

    /** Right password: clear the counter and the lock, and store the hash (rehashed if needed). */
    public function registerSuccess(int $customerId, string $hash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE dbo.customer_credential
             SET failed_attempts = 0, locked_until = NULL, password_hash = :hash, updated_at = SYSUTCDATETIME()
             WHERE id_customer = :id'
        );
        $statement->bindValue(':hash', $hash, PDO::PARAM_STR);
        $statement->bindValue(':id', $customerId, PDO::PARAM_INT);
        $statement->execute();
    }
}
