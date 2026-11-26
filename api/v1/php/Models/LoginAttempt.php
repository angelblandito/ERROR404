<?php
declare(strict_types=1);

namespace Apicore\Models;

use Apicore\Core\RateLimiter;
use Apicore\Core\TooManyRequestsError;
use PDO;

/**
 * LoginAttempt model: failed sign-ins per key in `login_attempts`
 * (script API_JAM/distribuida/09_tokens_y_accesos.sql).
 *
 * Layer: models. Each key counts its failures inside a time window; reaching
 * the limit locks it until the window passes. The keys are the SHA-256 of
 * "email|IP" or of the IP, so no email or address is stored in clear text.
 *
 * Without the table (node where script 09 was not run) the counters fall back
 * to Core\RateLimiter, the professor's file-based limiter.
 */
final class LoginAttempt extends BaseModel
{
    protected const TABLE_NAME = 'login_attempts';
    protected const ID_COLUMN = 'id_attempt';
    protected const COLUMNS = ['attempt_key', 'failures', 'first_failure_at', 'locked_until'];
    protected const INTEGER_COLUMNS = ['id_attempt', 'failures'];

    /** @var array<string, bool> */
    private static array $hasTable = [];

    public static function accountKey(string $email, string $ip): string
    {
        return 'cuenta-ip:' . hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }

    public static function ipKey(string $ip): string
    {
        return 'ip:' . hash('sha256', $ip);
    }

    /** True when dbo.login_attempts exists on this node (asked once per request). */
    private function usesTable(): bool
    {
        $key = spl_object_hash($this->pdo);
        if (!array_key_exists($key, self::$hasTable)) {
            $row = $this->pdo->query("SELECT CASE WHEN OBJECT_ID(N'dbo.login_attempts', N'U') IS NULL THEN 0 ELSE 1 END AS ok")->fetch();
            self::$hasTable[$key] = !empty($row['ok']);
        }
        return self::$hasTable[$key];
    }

    /** Seconds left before the key may try again; 0 when it is not locked. */
    public function blocked(string $key, int $limit, int $seconds): int
    {
        if (!$this->usesTable()) {
            try {
                (new RateLimiter($limit, $seconds))->check($key);
                return 0;
            } catch (TooManyRequestsError) {
                return $seconds;
            }
        }
        $statement = $this->pdo->prepare(
            'SELECT DATEDIFF(SECOND, SYSUTCDATETIME(), locked_until) AS wait
             FROM dbo.login_attempts
             WHERE attempt_key = ? AND locked_until > SYSUTCDATETIME()'
        );
        $statement->execute([$key]);
        $row = $statement->fetch();
        return $row ? max(1, (int) $row['wait']) : 0;
    }

    /**
     * Record a failure. Reaching the limit inside the window locks the key.
     * Failures older than one window no longer count: the count starts again at 1.
     */
    public function fail(string $key, int $limit, int $seconds): void
    {
        if (!$this->usesTable()) {
            (new RateLimiter($limit, $seconds))->fail($key);
            return;
        }
        $statement = $this->pdo->prepare(
            'MERGE dbo.login_attempts WITH (HOLDLOCK) AS t
             USING (SELECT :attempt_key AS attempt_key) AS s
                ON t.attempt_key = s.attempt_key
             WHEN MATCHED THEN UPDATE SET
                 failures = CASE WHEN t.first_failure_at < DATEADD(SECOND, -CAST(:window_a AS INT), SYSUTCDATETIME())
                                 THEN 1 ELSE t.failures + 1 END,
                 first_failure_at = CASE WHEN t.first_failure_at < DATEADD(SECOND, -CAST(:window_b AS INT), SYSUTCDATETIME())
                                         THEN SYSUTCDATETIME() ELSE t.first_failure_at END,
                 locked_until = CASE WHEN t.failures + 1 >= :limit_a
                                      AND t.first_failure_at >= DATEADD(SECOND, -CAST(:window_c AS INT), SYSUTCDATETIME())
                                     THEN DATEADD(SECOND, :lock_a, SYSUTCDATETIME()) ELSE t.locked_until END,
                 updated_at = SYSUTCDATETIME()
             WHEN NOT MATCHED THEN
                 INSERT (attempt_key, failures, first_failure_at, locked_until)
                 VALUES (s.attempt_key, 1, SYSUTCDATETIME(),
                         CASE WHEN 1 >= :limit_b THEN DATEADD(SECOND, :lock_b, SYSUTCDATETIME()) ELSE NULL END);'
        );
        // pdo_sqlsrv does not accept a repeated named placeholder, so each use has its own name
        $statement->bindValue(':attempt_key', $key, PDO::PARAM_STR);
        foreach (['window_a', 'window_b', 'window_c', 'lock_a', 'lock_b'] as $name) {
            $statement->bindValue(':' . $name, $seconds, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit_a', $limit, PDO::PARAM_INT);
        $statement->bindValue(':limit_b', $limit, PDO::PARAM_INT);
        $statement->execute();
    }

    /** Successful sign-in: forget the failures of that key and clean old rows. */
    public function reset(string $key): void
    {
        if (!$this->usesTable()) {
            (new RateLimiter())->reset($key);
            return;
        }
        $statement = $this->pdo->prepare('DELETE FROM dbo.login_attempts WHERE attempt_key = ?');
        $statement->execute([$key]);
        $this->pdo->exec(
            'DELETE FROM dbo.login_attempts
             WHERE updated_at < DATEADD(DAY, -1, SYSUTCDATETIME())
               AND (locked_until IS NULL OR locked_until < SYSUTCDATETIME())'
        );
    }
}
