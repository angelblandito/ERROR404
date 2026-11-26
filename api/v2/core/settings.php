<?php
/**
 * Traduccion de core/settings.py: ajustes de la API y validacion al arrancar.
 * Layer: core (cross-cutting). No importa controllers, models ni routers.
 *
 * Cada valor se busca, en este orden, en:
 *   1. Variables de entorno del servidor.
 *   2. api/v2/.env (KEY=VALUE).
 *   3. api/v2/config/settings.php o, si no existe, api/v1/php/config/settings.php
 *      (el mismo archivo que ya usa la tienda: asi las contrasenas viven en un solo lugar).
 *   4. El valor por defecto escrito aqui.
 */
final class Settings
{
    private static ?Settings $instance = null;

    /** Valores leidos de .env y de settings.php. */
    private array $values;

    public string $APP_TIMEZONE;
    /** Nodo que usan el CRUD generico y la tienda (central). */
    public string $JAM_DEFAULT_NODE;
    /** Dias que dura una sesion de cliente. */
    public int $SESSION_DAYS;
    /** Lista separada por comas. Vacio = solo el mismo origen (la pagina y la API viven juntas). */
    public string $CORS_ALLOW_ORIGIN;
    public bool $REQUIRE_HTTPS;
    public bool $TRUST_PROXY;
    public bool $APP_DEBUG;
    public int $PASSWORD_MIN_LENGTH;

    private function __construct(?string $root = null)
    {
        $root ??= dirname(__DIR__);
        $this->values = array_merge(
            self::read_settings_file($root),
            self::read_env_file($root . '/.env')
        );

        $flag = static fn(?string $v): bool => in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);

        $this->APP_TIMEZONE = (string) $this->get('APP_TIMEZONE', 'America/Tijuana');
        $this->JAM_DEFAULT_NODE = (string) $this->get('JAM_DEFAULT_NODE', 'central');
        $this->SESSION_DAYS = (int) $this->get('SESSION_DAYS', '7');
        $this->CORS_ALLOW_ORIGIN = (string) $this->get('CORS_ALLOW_ORIGIN', '');
        $this->REQUIRE_HTTPS = $flag($this->get('REQUIRE_HTTPS', '0'));
        $this->TRUST_PROXY = $flag($this->get('TRUST_PROXY', '0'));
        $this->APP_DEBUG = $flag($this->get('APP_DEBUG', '0'));
        $this->PASSWORD_MIN_LENGTH = (int) $this->get('PASSWORD_MIN_LENGTH', '8');
    }

    public static function instance(): Settings
    {
        return self::$instance ??= new Settings();
    }

    /** Solo para pruebas: vuelve a leer los ajustes desde otra carpeta. */
    public static function reload(?string $root = null): Settings
    {
        return self::$instance = new Settings($root);
    }

    /** Valor de un ajuste (entorno, .env, settings.php) o $default si esta vacio. */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        $value = $this->values[$key] ?? null;
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
        return $default;
    }

    /** CORS: lista de origenes permitidos. */
    public function cors_origins(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->CORS_ALLOW_ORIGIN))));
    }

    /** Lanza RuntimeException si un ajuste no tiene un valor valido (igual que validate() del profe). */
    public function validate(): void
    {
        if (!in_array($this->APP_TIMEZONE, timezone_identifiers_list(), true)) {
            throw new RuntimeException("APP_TIMEZONE no es una zona horaria valida: {$this->APP_TIMEZONE}");
        }
        if ($this->JAM_DEFAULT_NODE === '') {
            throw new RuntimeException('JAM_DEFAULT_NODE no puede estar vacio');
        }
        foreach ([
            'SESSION_DAYS' => $this->SESSION_DAYS,
        ] as $name => $value) {
            if ($value <= 0) {
                throw new RuntimeException("$name debe ser mayor que cero");
            }
        }
        if ($this->PASSWORD_MIN_LENGTH < 8) {
            throw new RuntimeException('PASSWORD_MIN_LENGTH no puede ser menor que 8');
        }
    }

    /** config/settings.php de v2 o, si no existe, el de v1. Devuelve [] si ninguno existe. */
    private static function read_settings_file(string $root): array
    {
        foreach ([$root . '/config/settings.php', dirname($root) . '/v1/php/config/settings.php'] as $path) {
            if (is_file($path)) {
                $loaded = require $path;
                return is_array($loaded) ? $loaded : [];
            }
        }
        return [];
    }

    /** Lee KEY=VALUE (ignora lineas vacias y comentarios). Archivo inexistente = []. */
    private static function read_env_file(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }
        return $values;
    }
}

/** Atajo para los servicios: Settings::instance()->get(). */
function jam_env(string $key, $default = null)
{
    return Settings::instance()->get($key, $default === null ? null : (string) $default);
}
