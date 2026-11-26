<?php
declare(strict_types=1);

namespace Apicore\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Database: one PDO connection per node and per request, shared by every model.
 *
 * Layer: core. PHP serves each request in its own process (or worker), so a
 * single connection per request is enough; there is no pool to manage like in
 * a long-running server.
 *
 * The store is a distributed SQL Server database (pdo_sqlsrv), so instead of
 * one server there are nodes:
 *   central  JAM_db       administration and master catalog
 *   tijuana  JAM_tijuana  fragment id_store = 1
 *   cdmx     JAM_cdmx     fragment id_store = 2
 *   online   JAM_online   fragment id_store = 4 + vertical fragment customer_profile
 *   cloud    JAM_cloud    full copy in Azure SQL with a mirror (failover group)
 *
 * When a remote node with 'retry_after' does not answer, it is skipped for that
 * many seconds so later requests do not wait for the timeout again.
 */
final class Database
{
    /** @var array<string, PDO|Throwable> */
    private static array $connections = [];
    /** @var array<string, array{endpoint: ?string, server: string, latency_ms: int}> */
    private static array $info = [];

    /** Connection to the default node (central). The models use this one. */
    public static function connection(): PDO
    {
        $name = Settings::instance()->JAM_DEFAULT_NODE;
        try {
            return self::node($name);
        } catch (Throwable $error) {
            Logger::exception('[database] ' . $name . ': ' . $error->getMessage());
            // The database is unreachable: answer 503 instead of a generic 500
            throw new ServiceUnavailableError('Database unavailable');
        }
    }

    /**
     * Enabled nodes and how to reach them.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function config(): array
    {
        $settings = Settings::instance();
        $env = static fn (string $key, ?string $default = null): ?string => $settings->get($key, $default);

        $local = static fn (string $database, string $role, string $label, ?int $storeId): array => [
            'server' => $env('JAM_LOCAL_SERVER', 'localhost'),
            'database' => $database,
            'user' => $env('JAM_LOCAL_USER'),
            'password' => $env('JAM_LOCAL_PASSWORD'),
            'trust_cert' => true,
            'role' => $role,
            'label' => $label,
            'id_store' => $storeId,
        ];

        $nodes = [
            'central' => $local((string) $env('JAM_CENTRAL_DB', 'JAM_db'), 'central', 'Nodo central', null),
            'tijuana' => $local((string) $env('JAM_TIJUANA_DB', 'JAM_tijuana'), 'sucursal', 'Sucursal Tijuana', 1),
            'cdmx' => $local((string) $env('JAM_CDMX_DB', 'JAM_cdmx'), 'sucursal', 'Sucursal CDMX', 2),
            'online' => $local((string) $env('JAM_ONLINE_DB', 'JAM_online'), 'tienda_en_linea', 'Tienda en línea', 4),
            'cloud' => [
                'server' => $env('JAM_CLOUD_SERVER', 'jam-fog.database.windows.net'),
                'database' => $env('JAM_CLOUD_DB', 'JAM_cloud'),
                'user' => $env('JAM_CLOUD_USER'),
                'password' => $env('JAM_CLOUD_PASSWORD'),
                'encrypt' => true,
                'timeout' => (int) $env('JAM_CLOUD_TIMEOUT', '4'),
                'retry_after' => (int) $env('JAM_CLOUD_RETRY_AFTER', '30'),
                'role' => 'nube',
                'label' => 'Azure SQL',
                'id_store' => null,
                'mirror' => ['server' => $env('JAM_CLOUD_MIRROR_SERVER', 'jam-fog.secondary.database.windows.net')],
            ],
        ];

        // The cloud node is enabled only with a user and a password
        if (empty($nodes['cloud']['user']) || empty($nodes['cloud']['password'])) {
            unset($nodes['cloud']);
        }

        // JAM_ENABLED_NODES limits the available nodes
        $enabled = $env('JAM_ENABLED_NODES');
        if ($enabled) {
            $names = array_filter(array_map('trim', explode(',', $enabled)));
            $nodes = array_intersect_key($nodes, array_flip($names));
        }
        return $nodes;
    }

    /**
     * Connection to one node. When it has a mirror and the primary does not
     * answer, it connects to the mirror. Connections and failures are
     * remembered for the rest of the request.
     */
    public static function node(string $name): PDO
    {
        if (isset(self::$connections[$name])) {
            if (self::$connections[$name] instanceof PDO) {
                return self::$connections[$name];
            }
            throw self::$connections[$name];
        }

        $nodes = self::config();
        if (!isset($nodes[$name])) {
            self::fail($name, new RuntimeException('Node not enabled: ' . $name));
        }
        $cfg = $nodes[$name];

        $retryIn = self::recentlyDown($name, $cfg);
        if ($retryIn !== null) {
            self::$info[$name] = ['endpoint' => null, 'server' => (string) $cfg['server'], 'latency_ms' => 0];
            self::fail($name, new RuntimeException("El nodo $name no respondió hace poco; se reintentará en {$retryIn} s."));
        }

        $start = microtime(true);
        $endpoint = 'principal';
        $server = (string) $cfg['server'];
        try {
            try {
                $pdo = self::open($cfg);
            } catch (PDOException $primaryError) {
                if (empty($cfg['mirror'])) {
                    throw $primaryError;
                }
                $mirror = array_merge($cfg, $cfg['mirror']);
                $pdo = self::open($mirror);
                $endpoint = 'espejo';
                $server = (string) $mirror['server'];
            }
        } catch (PDOException $error) {
            self::$info[$name] = ['endpoint' => null, 'server' => (string) $cfg['server'], 'latency_ms' => self::elapsed($start)];
            if (!empty($cfg['retry_after'])) {
                @touch(self::downMarker($name));
            }
            self::fail($name, $error);
        }

        if (!empty($cfg['retry_after'])) {
            @unlink(self::downMarker($name));
        }
        self::$info[$name] = ['endpoint' => $endpoint, 'server' => $server, 'latency_ms' => self::elapsed($start)];
        return self::$connections[$name] = $pdo;
    }

    /**
     * Server, endpoint (principal/espejo) and latency of the last connection to a node.
     *
     * @return array{endpoint: ?string, server: string, latency_ms: int}|null
     */
    public static function info(string $name): ?array
    {
        return self::$info[$name] ?? null;
    }

    /** Forget the connections of this request. */
    public static function close(): void
    {
        self::$connections = [];
        self::$info = [];
    }

    /** @param array<string, mixed> $cfg */
    public static function dsn(array $cfg): string
    {
        $dsn = 'sqlsrv:Server=' . $cfg['server'] . ';Database=' . $cfg['database'];
        $dsn .= ';LoginTimeout=' . (int) ($cfg['timeout'] ?? 5);
        if (!empty($cfg['encrypt'])) {
            $dsn .= ';Encrypt=yes;TrustServerCertificate=no';
        } elseif (!empty($cfg['trust_cert'])) {
            $dsn .= ';TrustServerCertificate=yes';
        }
        return $dsn;
    }

    /** @param array<string, mixed> $cfg */
    private static function open(array $cfg): PDO
    {
        // No user = Windows authentication
        $user = empty($cfg['user']) ? null : (string) $cfg['user'];
        $password = empty($cfg['password']) ? null : (string) $cfg['password'];
        return new PDO(self::dsn($cfg), $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private static function fail(string $name, Throwable $error): never
    {
        self::$connections[$name] = $error;
        throw $error;
    }

    private static function elapsed(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private static function downMarker(string $name): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jam_nodo_caido_' . preg_replace('/\W/', '', $name);
    }

    /**
     * Seconds left before a node that failed recently is tried again; null when it can be tried.
     *
     * @param array<string, mixed> $cfg
     */
    private static function recentlyDown(string $name, array $cfg): ?int
    {
        $wait = (int) ($cfg['retry_after'] ?? 0);
        $file = self::downMarker($name);
        if ($wait <= 0 || !is_file($file)) {
            return null;
        }
        $age = time() - (int) @filemtime($file);
        return $age < $wait ? $wait - $age : null;
    }
}
