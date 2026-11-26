<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\ForbiddenError;
use Apicore\Core\Security;
use Apicore\Core\Settings;
use Apicore\Core\UnauthorizedError;
use ValueError;

/**
 * Store access rules shared by the generated table controllers.
 *
 * Layer: controllers. The professor's UserController overrides getAll(),
 * getById(), authorize() and prepare() for its ownership rules; the store
 * tables share one set of rules, so they live here once:
 *
 *   - Without a session: read only the tables the store shows to everyone
 *     (PUBLIC_READ: promotion, state, season_theme).
 *   - admin (Administrador): every action on every table.
 *   - standard and priority (Estándar y Prioritario): only the public reads.
 *     Their own data is served by the store routes (/auth/me, /me/orders,
 *     /support/tickets), which already filter by their id.
 */
abstract class JamController extends BaseController
{
    /** The account types allowed to manage the store tables (account_tier.code). */
    public const PRIVILEGED_ROLES = ['admin'];

    protected const PUBLIC_READ = false;   // GET without a session (the router makes it public)
    protected const READ_ONLY = false;     // nobody writes it through the API (account_tier: the permissions)
    protected const HAS_PASSWORD = false;  // owner and employee: `password` becomes `password_hash`

    /** The caller has an Administrador account. */
    protected function isPrivileged(): bool
    {
        return in_array($this->authUser['role'] ?? null, self::PRIVILEGED_ROLES, true);
    }

    /** 401 without a session, 403 for a session that is not Administrador. */
    protected function requirePrivileged(): void
    {
        if ($this->authUser === null) {
            throw new UnauthorizedError('Unauthorized');
        }
        if (!$this->isPrivileged()) {
            throw new ForbiddenError('Esta operación es solo para administradores.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function getAll(?int $page = null, ?int $size = null): array
    {
        if (!static::PUBLIC_READ) {
            $this->requirePrivileged();
        }
        return parent::getAll($page, $size);
    }

    /** @return array<string, mixed>|null */
    public function getById(int|string $recordId): ?array
    {
        if (!static::PUBLIC_READ) {
            $this->requirePrivileged();
        }
        return parent::getById($recordId);
    }

    /** 403 on tables that only change with a SQL script. */
    protected function requireWritable(): void
    {
        if (static::READ_ONLY) {
            throw new ForbiddenError('Esta tabla solo se puede leer: se cambia con un script de SQL.');
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $this->requirePrivileged();
        $this->requireWritable();
        return parent::create($data);
    }

    /** Runs before PUT, PATCH and DELETE: only Administrador writes. */
    public function authorize(int|string $recordId, string $action, ?array $data = null): void
    {
        $this->requirePrivileged();
        $this->requireWritable();
    }

    /**
     * Store the password hashed. Required on create, optional on update
     * (omitted = unchanged), like the professor's UserController.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, int|string|null $recordId = null, bool $partial = false): array
    {
        // The hash is always calculated here, never accepted from the body
        if (array_key_exists('password_hash', $data)) {
            throw new ValueError('Unknown field(s): password_hash');
        }
        if (!static::HAS_PASSWORD) {
            return $data;
        }
        $password = $data['password'] ?? null;
        unset($data['password']);
        if ($password !== null && $password !== '') {
            $minimum = Settings::instance()->PASSWORD_MIN_LENGTH;
            if (!is_string($password) || strlen($password) < $minimum) {
                throw new ValueError('password must be ' . $minimum . ' chars min');
            }
            $data['password_hash'] = Security::hashPassword($password);
        } elseif ($recordId === null) {
            throw new ValueError('password is required');
        }
        return $data;
    }
}
