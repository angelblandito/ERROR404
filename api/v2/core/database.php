<?php
/**
 * Traduccion de core/database.py, extendida para la base distribuida.
 * Layer: core. Abre conexiones PDO a los nodos y nunca contiene logica de negocio.
 *
 * Nodos:
 *   central  JAM_db       administracion y catalogo maestro
 *   tijuana  JAM_tijuana  fragmento id_store = 1
 *   cdmx     JAM_cdmx     fragmento id_store = 2
 *   online   JAM_online   fragmento id_store = 4 + fragmento vertical customer_profile
 *   cloud    JAM_cloud    copia completa en Azure SQL con espejo (grupo de conmutacion)
 *
 * Una conexion por nodo y por peticion. Si un nodo remoto con 'retry_after' no responde,
 * se deja de intentar durante ese tiempo para no esperar el timeout en cada peticion.
 */
final class Database
{
    /** @var array<string, PDO|Throwable> */
    private static array $connections = [];
    /** @var array<string, array{endpoint: ?string, server: string, latency_ms: int}> */
    private static array $info = [];

    /** Configuracion de los nodos habilitados. */
    public static function config(): array
    {
        $local_server = jam_env('JAM_LOCAL_SERVER', 'localhost');
        $local_user = jam_env('JAM_LOCAL_USER');
        $local_password = jam_env('JAM_LOCAL_PASSWORD');

        $local = static fn(string $database, string $role, string $label, ?int $id_store): array => [
            'server' => $local_server,
            'database' => $database,
            'user' => $local_user,
            'password' => $local_password,
            'trust_cert' => true,
            'role' => $role,
            'label' => $label,
            'id_store' => $id_store,
        ];

        $nodes = [
            'central' => $local((string) jam_env('JAM_CENTRAL_DB', 'JAM_db'), 'central', 'Nodo central', null),
            'tijuana' => $local((string) jam_env('JAM_TIJUANA_DB', 'JAM_tijuana'), 'sucursal', 'Sucursal Tijuana', 1),
            'cdmx' => $local((string) jam_env('JAM_CDMX_DB', 'JAM_cdmx'), 'sucursal', 'Sucursal CDMX', 2),
            'online' => $local((string) jam_env('JAM_ONLINE_DB', 'JAM_online'), 'tienda_en_linea', 'Tienda en línea', 4),
            'cloud' => [
                'server' => jam_env('JAM_CLOUD_SERVER', 'jam-fog.database.windows.net'),
                'database' => jam_env('JAM_CLOUD_DB', 'JAM_cloud'),
                'user' => jam_env('JAM_CLOUD_USER'),
                'password' => jam_env('JAM_CLOUD_PASSWORD'),
                'encrypt' => true,
                'timeout' => (int) jam_env('JAM_CLOUD_TIMEOUT', '4'),
                'retry_after' => (int) jam_env('JAM_CLOUD_RETRY_AFTER', '30'),
                'role' => 'nube',
                'label' => 'Azure SQL',
                'id_store' => null,
                'mirror' => ['server' => jam_env('JAM_CLOUD_MIRROR_SERVER', 'jam-fog.secondary.database.windows.net')],
            ],
        ];

        // La nube solo se habilita con usuario y contrasena
        if (empty($nodes['cloud']['user']) || empty($nodes['cloud']['password'])) {
            unset($nodes['cloud']);
        }

        // JAM_ENABLED_NODES limita los nodos disponibles
        $enabled = jam_env('JAM_ENABLED_NODES');
        if ($enabled) {
            $names = array_filter(array_map('trim', explode(',', $enabled)));
            $nodes = array_intersect_key($nodes, array_flip($names));
        }
        return $nodes;
    }

    /** Conexion al nodo por defecto (central). 503 si no responde. */
    public static function connect(): PDO
    {
        $name = Settings::instance()->JAM_DEFAULT_NODE;
        try {
            return self::node($name);
        } catch (Throwable $error) {
            log_exception("[database] $name: " . $error->getMessage());
            throw new ServiceUnavailableError('Error interno de conexión a la base de datos.');
        }
    }

    /**
     * Conexion a un nodo. Si tiene espejo y el principal no responde, se conecta al espejo.
     * Conexiones y fallos se recuerdan durante la peticion.
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
            return self::fail($name, new RuntimeException("Nodo no habilitado: $name"));
        }
        $cfg = $nodes[$name];

        $retry_in = self::recently_down($name, $cfg);
        if ($retry_in !== null) {
            self::$info[$name] = ['endpoint' => null, 'server' => $cfg['server'], 'latency_ms' => 0];
            return self::fail($name, new RuntimeException("El nodo $name no respondió hace poco; se reintentará en {$retry_in} s."));
        }

        $start = microtime(true);
        $endpoint = 'principal';
        $server = $cfg['server'];
        try {
            try {
                $pdo = self::open($cfg);
            } catch (PDOException $primary_error) {
                if (empty($cfg['mirror'])) {
                    throw $primary_error;
                }
                $mirror_cfg = array_merge($cfg, $cfg['mirror']);
                $pdo = self::open($mirror_cfg);
                $endpoint = 'espejo';
                $server = $mirror_cfg['server'];
            }
        } catch (PDOException $error) {
            self::$info[$name] = ['endpoint' => null, 'server' => $cfg['server'], 'latency_ms' => self::elapsed($start)];
            if (!empty($cfg['retry_after'])) {
                @touch(self::down_marker($name));
            }
            return self::fail($name, $error);
        }

        if (!empty($cfg['retry_after'])) {
            @unlink(self::down_marker($name));
        }
        self::$info[$name] = ['endpoint' => $endpoint, 'server' => $server, 'latency_ms' => self::elapsed($start)];
        return self::$connections[$name] = $pdo;
    }

    /** Servidor, punto (principal/espejo) y latencia de la ultima conexion a un nodo. */
    public static function info(string $name): ?array
    {
        return self::$info[$name] ?? null;
    }

    /** Cierra las conexiones de esta peticion. */
    public static function close(): void
    {
        self::$connections = [];
        self::$info = [];
    }

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

    private static function open(array $cfg): PDO
    {
        // Sin usuario = autenticacion de Windows
        $user = empty($cfg['user']) ? null : $cfg['user'];
        $password = empty($cfg['password']) ? null : $cfg['password'];
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

    private static function down_marker(string $name): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jam_nodo_caido_' . preg_replace('/\W/', '', $name);
    }

    /** Segundos que faltan para volver a intentar un nodo que fallo hace poco; null si se puede intentar. */
    private static function recently_down(string $name, array $cfg): ?int
    {
        $wait = (int) ($cfg['retry_after'] ?? 0);
        $file = self::down_marker($name);
        if ($wait <= 0 || !is_file($file)) {
            return null;
        }
        $age = time() - (int) @filemtime($file);
        return $age < $wait ? $wait - $age : null;
    }
}

/* Atajos con los nombres que usan los servicios de la base distribuida. */
function jam_nodes_config(): array
{
    return Database::config();
}

function jam_node(string $name): PDO
{
    return Database::node($name);
}

function jam_node_info(string $name): ?array
{
    return Database::info($name);
}
