<?php
require_once(__DIR__ . '/../config/nodes.php');

function jam_dsn(array $cfg): string
{
    $dsn = 'sqlsrv:Server=' . $cfg['server'] . ';Database=' . $cfg['database'];
    $dsn .= ';LoginTimeout=' . (int)($cfg['timeout'] ?? 5);

    if (!empty($cfg['encrypt'])) {
        $dsn .= ';Encrypt=yes;TrustServerCertificate=no';
    } elseif (!empty($cfg['trust_cert'])) {
        $dsn .= ';TrustServerCertificate=yes';
    }
    return $dsn;
}

function jam_open(array $cfg): PDO
{
    $user = empty($cfg['user']) ? null : $cfg['user'];
    $password = empty($cfg['password']) ? null : $cfg['password'];

    $pdo = new PDO(jam_dsn($cfg), $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

/*
 * Nodos remotos con 'retry_after': si no respondieron hace poco, se omiten
 * sin volver a esperar el tiempo de conexión. El aviso se guarda en un
 * archivo temporal para que lo vean las siguientes peticiones.
 */
function jam_down_marker(string $name): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jam_nodo_caido_' . preg_replace('/\W/', '', $name);
}

function jam_recently_down(string $name, array $cfg): ?int
{
    $wait = (int)($cfg['retry_after'] ?? 0);
    $file = jam_down_marker($name);
    if ($wait <= 0 || !is_file($file)) {
        return null;
    }
    $age = time() - (int)@filemtime($file);
    return $age < $wait ? $wait - $age : null;
}

/*
 * Devuelve la conexión a un nodo. Si el nodo tiene espejo ('mirror') y el
 * servidor principal no responde, se conecta al espejo automáticamente.
 * Las conexiones (y los fallos) se guardan durante la petición para no
 * esperar el tiempo de espera más de una vez por nodo.
 */
function jam_node(string $name): PDO
{
    global $JAM_CONNECTIONS, $JAM_CONNECTION_INFO;

    if (isset($JAM_CONNECTIONS[$name])) {
        if ($JAM_CONNECTIONS[$name] instanceof PDO) {
            return $JAM_CONNECTIONS[$name];
        }
        throw $JAM_CONNECTIONS[$name];
    }

    $nodes = jam_nodes_config();
    if (!isset($nodes[$name])) {
        $error = new RuntimeException("Nodo no habilitado: $name");
        $JAM_CONNECTIONS[$name] = $error;
        throw $error;
    }

    $cfg = $nodes[$name];

    $retryIn = jam_recently_down($name, $cfg);
    if ($retryIn !== null) {
        $JAM_CONNECTION_INFO[$name] = ['endpoint' => null, 'server' => $cfg['server'], 'latency_ms' => 0];
        $error = new RuntimeException("El nodo $name no respondió hace poco; se reintentará en {$retryIn} s.");
        $JAM_CONNECTIONS[$name] = $error;
        throw $error;
    }

    $start = microtime(true);
    $endpoint = 'principal';
    $server = $cfg['server'];

    try {
        try {
            $pdo = jam_open($cfg);
        } catch (PDOException $primaryError) {
            if (empty($cfg['mirror'])) {
                throw $primaryError;
            }
            $mirrorCfg = array_merge($cfg, $cfg['mirror']);
            $pdo = jam_open($mirrorCfg);
            $endpoint = 'espejo';
            $server = $mirrorCfg['server'];
        }
    } catch (PDOException $e) {
        $JAM_CONNECTION_INFO[$name] = [
            'endpoint' => null,
            'server' => $cfg['server'],
            'latency_ms' => (int)round((microtime(true) - $start) * 1000),
        ];
        $JAM_CONNECTIONS[$name] = $e;
        if (!empty($cfg['retry_after'])) {
            @touch(jam_down_marker($name));
        }
        throw $e;
    }

    if (!empty($cfg['retry_after'])) {
        @unlink(jam_down_marker($name));
    }

    $JAM_CONNECTION_INFO[$name] = [
        'endpoint' => $endpoint,
        'server' => $server,
        'latency_ms' => (int)round((microtime(true) - $start) * 1000),
    ];
    $JAM_CONNECTIONS[$name] = $pdo;
    return $pdo;
}

function jam_node_info(string $name): ?array
{
    global $JAM_CONNECTION_INFO;
    return $JAM_CONNECTION_INFO[$name] ?? null;
}

$JAM_CONNECTIONS = [];
$JAM_CONNECTION_INFO = [];

/* Conexión por defecto que usan los modelos CRUD (models/*.php). */
$DB_Connector = null;
$DB_ConnectionError = null;

try {
    $DB_Connector = jam_node(JAM_DEFAULT_NODE);
} catch (Throwable $e) {
    error_log('Error de conexión a la BD (' . JAM_DEFAULT_NODE . '): ' . $e->getMessage());
    $DB_ConnectionError = 'Error interno de conexión a la base de datos.';
}
