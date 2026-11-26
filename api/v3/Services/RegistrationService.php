<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use PDO;

/**
 * Registration with email confirmation.
 *
 *   POST /auth/register  {first_name, ..., email, password, accepts_terms, return_url?}
 *        Validates the form, saves it in dbo.customer_signup (the password already
 *        hashed) and emails a link #/confirmar?token=... No account is created.
 *   POST /auth/confirm   {token}
 *        AuthController::confirm creates the account with claim() + markConfirmed()
 *        in one transaction and signs the customer in.
 *
 * Only the SHA-256 of the token is stored, like password_reset and users_tokens:
 * someone who reads the table cannot confirm somebody else's registration.
 *
 * Layer: services. Business rules and SQL, no HTTP.
 */
final class RegistrationService
{
    /** Hours the confirmation link is valid. */
    public const HOURS = 24;

    /** Seconds before the same email can ask for another confirmation email. */
    public const COOLDOWN_SECONDS = 60;

    /** @return array{pending: true, email: string, hours: int} */
    public static function start(array $body): array
    {
        $data = StoreService::validateRegistration($body);
        $pdo = StoreService::db();

        $existing = StoreService::rows($pdo, '
            SELECT 1 AS found FROM dbo.customer
            WHERE deleted = 0 AND (email = :email OR phone = :phone)',
            [':email' => $data['email'], ':phone' => $data['phone']]);
        if ($existing) {
            throw new AppError('Ya existe una cuenta con ese correo o teléfono. Inicia sesión o recupera tu contraseña.', 409);
        }

        $recent = StoreService::rows($pdo, '
            SELECT 1 AS found FROM dbo.customer_signup
            WHERE email = :email AND confirmed_at IS NULL
              AND created_at > DATEADD(SECOND, -CAST(:cooldown AS INT), SYSUTCDATETIME())',
            [':email' => $data['email'], ':cooldown' => self::COOLDOWN_SECONDS]);
        if ($recent) {
            throw new AppError('Ya te enviamos el correo de confirmación. Revisa tu bandeja (y la de spam) o espera un minuto para pedir otro.', 429);
        }

        // Housekeeping: drop expired signups (they keep a password hash)
        $pdo->exec('DELETE FROM dbo.customer_signup WHERE confirmed_at IS NULL AND expires_at < SYSUTCDATETIME()');

        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO dbo.customer_signup (token_hash, email, phone, first_name, payload, expires_at, source_node)
            VALUES (:token_hash, :email, :phone, :first_name, :payload, DATEADD(HOUR, :hours, SYSUTCDATETIME()), 'web_node')");
        $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':first_name', $data['first_name'], PDO::PARAM_STR);
        $stmt->bindValue(':payload', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PDO::PARAM_STR);
        $stmt->bindValue(':hours', self::HOURS, PDO::PARAM_INT);
        $stmt->execute();

        NotificationService::signupConfirm(
            $pdo,
            $data['email'],
            $data['first_name'],
            RecoveryService::pageLink($body, '#/confirmar?token=' . $token),
            self::HOURS,
        );

        return ['pending' => true, 'email' => $data['email'], 'hours' => self::HOURS];
    }

    /** The token of the link (64 hex characters) or a 400. */
    public static function checkToken(string $token): string
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new AppError('El enlace de confirmación no es válido. Regístrate de nuevo.', 400);
        }
        return $token;
    }

    /**
     * Lock the pending signup of the link (inside the caller's transaction) and
     * return the validated form saved in step 1.
     *
     * @return array<string, mixed>
     */
    public static function claim(PDO $pdo, string $token): array
    {
        $rows = StoreService::rows($pdo, '
            SELECT payload
            FROM dbo.customer_signup WITH (UPDLOCK, ROWLOCK)
            WHERE token_hash = :token_hash AND confirmed_at IS NULL AND expires_at > SYSUTCDATETIME()',
            [':token_hash' => hash('sha256', self::checkToken($token))]);
        if (!$rows) {
            throw new AppError('El enlace venció o ya se usó. Si ya confirmaste, inicia sesión; si no, regístrate de nuevo.', 410);
        }
        $data = json_decode((string) $rows[0]['payload'], true);
        if (!is_array($data) || empty($data['email']) || empty($data['password_hash'])) {
            throw new AppError('No pudimos leer tu registro. Regístrate de nuevo.', 410);
        }
        return $data;
    }

    public static function markConfirmed(PDO $pdo, string $token, int $customerId): void
    {
        $stmt = $pdo->prepare('
            UPDATE dbo.customer_signup
            SET confirmed_at = SYSUTCDATETIME(), id_customer = :id_customer, payload = N\'{}\'
            WHERE token_hash = :token_hash');
        $stmt->bindValue(':id_customer', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', hash('sha256', self::checkToken($token)), PDO::PARAM_STR);
        $stmt->execute();
    }
}
