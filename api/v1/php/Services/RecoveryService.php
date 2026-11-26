<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use Apicore\Core\Settings;
use PDO;
use Throwable;

/**
 * Password recovery.
 *
 *   POST /auth/forgot  {email, return_url}  Saves the email with the reset link
 *   POST /auth/reset   {token, password}    Changes the password and closes every session
 *
 * /auth/forgot answers the same whether the email exists or not, so it never
 * reveals which accounts are registered. The link is single use, expires in
 * MINUTES and only its SHA-256 is stored.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class RecoveryService
{
    public const MINUTES = 30;

    public const COOLDOWN_SECONDS = 60;

    /**
     * Link of the reset email. The page sends where it lives (return_url), but
     * the Host header of a request can be forged, so the link may only point to
     * a known address: the one in JAM_STORE_URL, an origin of CORS_ALLOW_ORIGIN
     * or this same computer (localhost). Anything else gets the JAM_STORE_URL
     * link, so nobody can make the store email a link to another site.
     */
    public static function link(array $body, string $token): string
    {
        $fragment = '#/restablecer?token=' . $token;
        $parts = parse_url(StoreService::field($body, 'return_url', ''));
        if ($parts && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && isset($parts['host'])) {
            $host = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
            if (in_array($host, self::allowedHosts(), true) || in_array(strtolower($parts['host']), ['localhost', '127.0.0.1'], true)) {
                return $parts['scheme'] . '://' . $host . ($parts['path'] ?? '/') . $fragment;
            }
        }
        return NotificationService::storeLink($fragment);
    }

    /** @return list<string> host[:port] of JAM_STORE_URL and of every CORS origin */
    private static function allowedHosts(): array
    {
        $settings = Settings::instance();
        $urls = array_merge(
            [(string) $settings->get('JAM_STORE_URL', '')],
            array_map('trim', explode(',', $settings->CORS_ALLOW_ORIGIN)),
        );
        $hosts = [];
        foreach ($urls as $url) {
            $parts = parse_url($url);
            if ($parts && isset($parts['host'])) {
                $hosts[] = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
            }
        }
        return $hosts;
    }

    public static function forgot(array $body): array
    {
        $email = strtolower(trim(StoreService::field($body, 'email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppError('Escribe un correo válido.', 400);
        }

        $pdo = StoreService::db();
        $rows = StoreService::rows($pdo, '
            SELECT c.id_customer, c.first_name,
                   (SELECT MAX(pr.created_at) FROM dbo.password_reset AS pr
                    WHERE pr.id_customer = c.id_customer
                      AND pr.created_at > DATEADD(SECOND, -CAST(:cooldown AS INT), SYSUTCDATETIME())) AS recent
            FROM dbo.customer AS c
            JOIN dbo.customer_credential AS cr ON cr.id_customer = c.id_customer
            WHERE c.email = :email AND c.deleted = 0',
            [':email' => $email, ':cooldown' => self::COOLDOWN_SECONDS]);

        if ($rows && $rows[0]['recent'] === null) {
            $idCustomer = (int)$rows[0]['id_customer'];
            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare('
                INSERT INTO dbo.password_reset (token_hash, id_customer, expires_at)
                VALUES (:token_hash, :id_customer, DATEADD(MINUTE, :minutes, SYSUTCDATETIME()))');
            $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
            $stmt->bindValue(':id_customer', $idCustomer, PDO::PARAM_INT);
            $stmt->bindValue(':minutes', self::MINUTES, PDO::PARAM_INT);
            $stmt->execute();

            NotificationService::passwordReset($pdo, $idCustomer, $rows[0]['first_name'], self::link($body, $token), self::MINUTES);
        }

        return ['sent' => true, 'minutes' => self::MINUTES];
    }

    public static function reset(array $body): array
    {
        $token = strtolower(trim(StoreService::field($body, 'token', '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new AppError('El enlace no es válido. Solicita uno nuevo.', 400);
        }
        $hash = StoreService::passwordHash(StoreService::field($body, 'password', ''));

        $pdo = StoreService::db();
        $pdo->beginTransaction();
        try {
            $rows = StoreService::rows($pdo, '
                SELECT id_customer
                FROM dbo.password_reset WITH (UPDLOCK, ROWLOCK)
                WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > SYSUTCDATETIME()',
                [':token_hash' => hash('sha256', $token)]);
            if (!$rows) {
                throw new AppError('El enlace venció o ya se usó. Solicita uno nuevo.', 410);
            }
            $idCustomer = (int)$rows[0]['id_customer'];

            $stmt = $pdo->prepare('
                UPDATE dbo.customer_credential
                SET password_hash = :hash, failed_attempts = 0, locked_until = NULL, updated_at = SYSUTCDATETIME()
                WHERE id_customer = :id');
            $stmt->execute([':hash' => $hash, ':id' => $idCustomer]);

            $stmt = $pdo->prepare('
                UPDATE dbo.password_reset SET used_at = SYSUTCDATETIME()
                WHERE id_customer = :id AND used_at IS NULL');
            $stmt->execute([':id' => $idCustomer]);

            TokenService::revokeAllOf('customer', $idCustomer);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['reset' => true];
    }
}
