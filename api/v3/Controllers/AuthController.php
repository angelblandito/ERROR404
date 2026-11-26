<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\AppError;
use Apicore\Core\Security;
use Apicore\Core\Settings;
use Apicore\Core\TooManyRequestsError;
use Apicore\Core\UnauthorizedError;
use Apicore\Models\CustomerCredential;
use Apicore\Models\LoginAttempt;
use Apicore\Models\UserToken;
use Apicore\Services\NotificationService;
use Apicore\Services\RecoveryService;
use Apicore\Services\RegistrationService;
use Apicore\Services\StoreService;
use Apicore\Services\TokenService;
use PDO;
use PDOException;
use Throwable;
use ValueError;

/**
 * Auth controller: login (token generation), logout (token revocation) and the
 * customer account (sign up, profile, password recovery).
 *
 * Layer: controllers. It orchestrates the account models, the token service
 * and the login lockout; it never touches the socket. The accounts are the
 * customers of the store (customer + customer_credential) and the role of the
 * token is their account type: standard, priority or admin.
 */
final class AuthController extends BaseController
{
    protected const MODEL_CLASS = CustomerCredential::class;

    // Verified against a dummy hash when the email does not exist, so the
    // response time matches a real check and accounts cannot be enumerated by timing
    private static ?string $dummyHash = null;

    /**
     * Validate credentials and return a new token (an account may hold many at once).
     *
     * Accepts {"Username", "Password", "Persistent"} and the body the store
     * page sends {"email", "password"}. `Persistent: true` issues
     * a token without expiration ("keep me signed in"); it stays valid until it
     * is revoked (logout) or the account is deleted.
     *
     * Failed attempts (window of StoreService::LOCK_MINUTES):
     *   - same email from the same IP: only that connection is stopped, the
     *     owner of the account can still sign in from theirs;
     *   - any email from the same IP: stops whoever tries many accounts;
     *   - the whole customer account is locked only after many failures from
     *     several IPs (a distributed attack).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function login(array $data): array
    {
        $email = $data['Username'] ?? $data['email'] ?? null;
        $password = $data['Password'] ?? $data['password'] ?? null;
        if (!$email || !$password) {
            throw new ValueError('Escribe tu correo y contraseña.');
        }
        if (!is_string($email) || !is_string($password)) {
            throw new ValueError('Username and Password must be strings');
        }
        $persistent = $data['Persistent'] ?? false;
        if (!is_bool($persistent)) {
            throw new ValueError('Persistent must be a boolean');
        }
        $email = strtolower(trim($email));

        $attempts = new LoginAttempt();
        $window = StoreService::LOCK_MINUTES * 60;
        $pairKey = LoginAttempt::accountKey($email, $this->clientIp);
        $ipKey = LoginAttempt::ipKey($this->clientIp);
        $wait = max(
            $attempts->blocked($pairKey, StoreService::FAILS_PER_ACCOUNT_IP, $window),
            $attempts->blocked($ipKey, StoreService::FAILS_PER_IP, $window),
        );
        if ($wait > 0) {
            throw new TooManyRequestsError(
                'Demasiados intentos fallidos desde tu conexión. Intenta de nuevo en ' . self::waitText($wait) . '.'
            );
        }
        $failed = static function () use ($attempts, $pairKey, $ipKey, $window): UnauthorizedError {
            $attempts->fail($pairKey, StoreService::FAILS_PER_ACCOUNT_IP, $window);
            $attempts->fail($ipKey, StoreService::FAILS_PER_IP, $window);
            // Same error for unknown email / wrong password / inactive account: do not reveal which
            return new UnauthorizedError('Correo o contraseña incorrectos.');
        };

        $account = $this->model->findByEmail($email);
        if ($account === null) {
            Security::verifyPassword($password, self::dummyHash());  // uniform response time
            throw $failed();
        }
        if ($account['is_locked']) {
            throw new TooManyRequestsError(
                'Demasiados intentos fallidos. Intenta de nuevo en ' . StoreService::LOCK_MINUTES . ' minutos.'
            );
        }
        if (!Security::verifyPassword($password, $account['password_hash'])) {
            $this->model->registerFailure(
                $account['id_customer'],
                StoreService::MAX_FAILED_LOGINS,
                StoreService::LOCK_MINUTES,
            );
            throw $failed();
        }

        $hash = Security::needsRehash($account['password_hash'])
            ? Security::hashPassword($password)
            : $account['password_hash'];
        $this->model->registerSuccess($account['id_customer'], $hash);
        $attempts->reset($pairKey);

        $profile = StoreService::profile(StoreService::db(), $account['id_customer']);
        $response = $this->issueToken($account['id_customer'], $profile['account']['tier'], $persistent);
        $response['customer'] = $profile;
        return $response;
    }

    /**
     * "Crear cuenta", step 1: validate the form, keep it as a pending signup and
     * email a confirmation link. No account exists until the link is opened.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function register(array $body): array
    {
        return RegistrationService::start($body);
    }

    /**
     * "Crear cuenta", step 2: the link of the email. Creates the customer with an
     * Estándar account (the type never comes from the request) and signs it in.
     *
     * @param array<string, mixed> $body {token}
     * @return array<string, mixed>
     */
    public function confirm(array $body): array
    {
        $pdo = StoreService::db();
        $pdo->beginTransaction();
        try {
            $data = RegistrationService::claim($pdo, (string) ($body['token'] ?? ''));

            $statement = $pdo->prepare("
                SET NOCOUNT ON;
                INSERT INTO dbo.customer (id_loyalty_level, id_store, id_address, first_name, last_name_paternal,
                                          last_name_maternal, birth_date, sex, phone, email, accepts_marketing, source_node)
                VALUES (1, :id_store, NULL, :first_name, :last_name_paternal, :last_name_maternal,
                        :birth_date, :sex, :phone, :email, :accepts_marketing, 'web_node');
                SELECT CAST(SCOPE_IDENTITY() AS INT) AS NewId;");
            $statement->bindValue(':id_store', StoreService::ONLINE_ID, PDO::PARAM_INT);
            foreach (['first_name', 'last_name_paternal', 'last_name_maternal', 'birth_date', 'sex', 'phone', 'email'] as $field) {
                $value = $data[$field] ?? null;
                $statement->bindValue(':' . $field, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $statement->bindValue(':accepts_marketing', (int) ($data['accepts_marketing'] ?? 0), PDO::PARAM_INT);
            $statement->execute();
            while ($statement->columnCount() === 0 && $statement->nextRowset()) {
            }
            $customerId = (int) $statement->fetchColumn();

            $statement = $pdo->prepare('
                INSERT INTO dbo.customer_credential (id_customer, password_hash, terms_accepted_at)
                VALUES (:id, :hash, SYSUTCDATETIME())');
            $statement->bindValue(':id', $customerId, PDO::PARAM_INT);
            $statement->bindValue(':hash', (string) $data['password_hash'], PDO::PARAM_STR);
            $statement->execute();

            RegistrationService::markConfirmed($pdo, (string) $body['token'], $customerId);

            $response = $this->issueToken($customerId, 'standard', false);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof PDOException && in_array((int) ($error->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw new AppError('Ya existe una cuenta con ese correo o teléfono. Inicia sesión.', 409);
            }
            throw $error;
        }

        NotificationService::safely(static function () use ($pdo, $customerId, $data): void {
            NotificationService::welcome($pdo, $customerId, (string) $data['first_name']);
        });

        $response['customer'] = StoreService::profile($pdo, $customerId);
        return $response;
    }

    /** Revoke the token used in the current request. */
    public function logout(): void
    {
        TokenService::revokeToken((string) $this->authToken);
    }

    /**
     * Profile of the customer of the session, with its account type.
     *
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return StoreService::profile(StoreService::db(), StoreService::requireCustomer($this->authUser));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function forgot(array $body): array
    {
        return RecoveryService::forgot($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function reset(array $body): array
    {
        return RecoveryService::reset($body);
    }

    /**
     * Housekeeping, new token and the login response (token fields plus the
     * role, which is the account type).
     *
     * @return array<string, mixed>
     */
    private function issueToken(int $customerId, string $role, bool $persistent): array
    {
        $settings = Settings::instance();
        $tokens = new UserToken();
        try {
            $tokens->deleteExpired();  // housekeeping: drop tokens whose expiration passed
            [$token, $payload] = TokenService::generateToken(
                $customerId,
                $role,
                $settings->TOKEN_LIFETIME_MINUTES,
                $settings->TOKEN_MIN_REMAINING_MINUTES,
                $persistent,
                $tokens,
            );
        } finally {
            $tokens->close();
        }
        return TokenService::buildTokenResponse($token, $payload);
    }

    /** "45 segundos", "3 minutos". */
    private static function waitText(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ($seconds === 1 ? ' segundo' : ' segundos');
        }
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ($minutes === 1 ? ' minuto' : ' minutos');
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Security::hashPassword('timing-equalizer');
    }
}
