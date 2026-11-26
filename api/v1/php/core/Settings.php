<?php
declare(strict_types=1);

namespace Apicore\Core;

use DateTimeZone;
use Exception;
use ValueError;

/**
 * Settings: custom .env reader exposed as a single instance.
 *
 * Layer: core (cross-cutting). It must never import from Controllers, Models or Routers.
 *
 * Each value is looked up in this order:
 *   1. Real environment variables of the server.
 *   2. api/v3/.env (KEY=VALUE).
 *   3. api/v2/config/settings.php or api/v1/php/config/settings.php, the file the store already
 *      uses, so the database passwords live in a single place.
 *   4. The default written here.
 */
final class Settings
{
    // Windows authentication (no user) is valid for the local SQL Server, so only the secret is required
    private const REQUIRED = ['JWT_SECRET'];

    public string $APP_HOST;
    public int $APP_PORT;
    public string $APP_TIMEZONE;
    public string $JWT_SECRET;
    public string $JWT_ALGORITHM;
    public int $TOKEN_LIFETIME_MINUTES;
    public int $TOKEN_MIN_REMAINING_MINUTES;
    public string $CORS_ALLOW_ORIGIN;

    /** Node used by the generic CRUD and the store (central). */
    public string $JAM_DEFAULT_NODE;
    public int $PASSWORD_MIN_LENGTH;
    /** 1 = refuse plain http (the token would travel in clear text). */
    public bool $REQUIRE_HTTPS;
    /** Behind Caddy every request comes from 127.0.0.1: 1 = read the client IP from X-Forwarded-For. */
    public bool $TRUST_PROXY;
    /** Request budget per IP on the store routes (0 turns it off, e.g. for load tests). */
    public bool $RATE_LIMIT;

    /** @var array<string, string> values read from .env and settings.php */
    private array $values;

    private static ?self $instance = null;

    public function __construct(?string $root = null)
    {
        $root ??= dirname(__DIR__);
        $this->values = array_merge(self::readSettingsFile($root), self::readEnvFile($root . '/.env'));

        $flag = static fn (?string $value): bool => in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);

        $this->APP_HOST = (string) $this->get('APP_HOST', 'localhost');
        $this->APP_PORT = (int) $this->get('APP_PORT', '8000');
        $this->APP_TIMEZONE = (string) $this->get('APP_TIMEZONE', 'America/Tijuana');
        try {
            new DateTimeZone($this->APP_TIMEZONE);
        } catch (Exception) {
            throw new ValueError('APP_TIMEZONE is not a valid IANA timezone: ' . $this->APP_TIMEZONE);
        }
        $this->JWT_SECRET = (string) $this->get('JWT_SECRET', '');
        $this->JWT_ALGORITHM = (string) $this->get('JWT_ALGORITHM', 'HS256');
        $this->TOKEN_LIFETIME_MINUTES = (int) $this->get('TOKEN_LIFETIME_MINUTES', '60');
        $this->TOKEN_MIN_REMAINING_MINUTES = (int) $this->get('TOKEN_MIN_REMAINING_MINUTES', '10');
        // Empty = same origin only (the page and the API are served together); '*' allows any origin
        $this->CORS_ALLOW_ORIGIN = (string) $this->get('CORS_ALLOW_ORIGIN', '');

        $this->JAM_DEFAULT_NODE = (string) $this->get('JAM_DEFAULT_NODE', 'central');
        $this->PASSWORD_MIN_LENGTH = (int) $this->get('PASSWORD_MIN_LENGTH', '8');
        $this->REQUIRE_HTTPS = $flag($this->get('REQUIRE_HTTPS', '0'));
        $this->TRUST_PROXY = $flag($this->get('TRUST_PROXY', '0'));
        $this->RATE_LIMIT = $flag($this->get('RATE_LIMIT', '1'));
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Tests only: read the settings again from another folder. */
    public static function reload(?string $root = null): self
    {
        return self::$instance = new self($root);
    }

    /** Value of a setting (environment, .env, settings.php) or $default when it is empty. */
    public function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        $value = $this->values[$key] ?? null;
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
        return $default;
    }

    /**
     * Raise ValueError listing every required setting that is missing.
     *
     * Called at startup, so a misconfigured server fails immediately instead of
     * on the first request.
     */
    public function validate(): void
    {
        $missing = [];
        foreach (self::REQUIRED as $name) {
            if ($this->{$name} === '') {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw new ValueError('Missing required setting(s): ' . implode(', ', $missing));
        }
        // A short key can be guessed by trying keys offline against one stolen token
        if (strlen($this->JWT_SECRET) < 32) {
            throw new ValueError('JWT_SECRET must be at least 32 characters');
        }
        if ($this->TOKEN_LIFETIME_MINUTES <= 0) {
            throw new ValueError('TOKEN_LIFETIME_MINUTES must be greater than zero');
        }
        if ($this->PASSWORD_MIN_LENGTH < 8) {
            throw new ValueError('PASSWORD_MIN_LENGTH cannot be less than 8');
        }
    }

    /**
     * config/settings.php of v2 or, when it does not exist, the one of v1. [] when neither exists.
     *
     * @return array<string, string>
     */
    private static function readSettingsFile(string $root): array
    {
        $api = dirname($root);
        foreach ([$api . '/v2/config/settings.php', $api . '/v1/php/config/settings.php'] as $path) {
            if (is_file($path)) {
                $loaded = require $path;
                return is_array($loaded) ? array_map('strval', array_filter($loaded, 'is_scalar')) : [];
            }
        }
        return [];
    }

    /**
     * Parse KEY=VALUE lines (ignores blank lines and # comments). Missing file -> [].
     *
     * @return array<string, string>
     */
    private static function readEnvFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), '"\'');
        }
        return $values;
    }
}
