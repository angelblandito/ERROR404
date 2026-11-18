<?php
/*
 * Configuración de los nodos de la base de datos distribuida.
 *
 * Cada valor se toma, en este orden, de:
 *   1. Variables de entorno (en Azure App Service: "Configuración > Variables de entorno").
 *   2. config/settings.php (copia local de settings.example.php, no se sube a GitHub).
 *   3. El valor por defecto escrito aquí.
 */

$JAM_SETTINGS = [];
if (is_file(__DIR__ . '/settings.php')) {
    $loaded = require __DIR__ . '/settings.php';
    if (is_array($loaded)) {
        $JAM_SETTINGS = $loaded;
    }
}

function jam_env(string $key, $default = null)
{
    global $JAM_SETTINGS;

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($JAM_SETTINGS[$key]) && $JAM_SETTINGS[$key] !== '') {
        return $JAM_SETTINGS[$key];
    }
    return $default;
}

define('JAM_DEFAULT_NODE', jam_env('JAM_DEFAULT_NODE', 'central'));

/*
 * Nodos:
 *   central  JAM_db       administración y catálogo maestro (publicador de la replicación)
 *   tijuana  JAM_tijuana  fragmento id_store = 1
 *   cdmx     JAM_cdmx     fragmento id_store = 2
 *   online   JAM_online   fragmento id_store = 4 + fragmento vertical customer_profile
 *   cloud    JAM_cloud    réplica completa en Azure SQL con espejo (failover group)
 */
function jam_nodes_config(): array
{
    $localServer = jam_env('JAM_LOCAL_SERVER', 'jonathan\SQLEXPRESS');
    $localUser = jam_env('JAM_LOCAL_USER');
    $localPassword = jam_env('JAM_LOCAL_PASSWORD');

    $local = function (string $database, string $role, string $label, ?int $idStore) use ($localServer, $localUser, $localPassword) {
        return [
            'server' => $localServer,
            'database' => $database,
            'user' => $localUser,
            'password' => $localPassword,
            'trust_cert' => true,
            'role' => $role,
            'label' => $label,
            'id_store' => $idStore,
        ];
    };

    $nodes = [
        'central' => $local(jam_env('JAM_CENTRAL_DB', 'JAM_db'), 'central', 'Nodo central', null),
        'tijuana' => $local(jam_env('JAM_TIJUANA_DB', 'JAM_tijuana'), 'sucursal', 'Sucursal Tijuana', 1),
        'cdmx' => $local(jam_env('JAM_CDMX_DB', 'JAM_cdmx'), 'sucursal', 'Sucursal CDMX', 2),
        'online' => $local(jam_env('JAM_ONLINE_DB', 'JAM_online'), 'tienda_en_linea', 'Tienda en línea', 4),
        'cloud' => [
            'server' => jam_env('JAM_CLOUD_SERVER', 'jam-fog.database.windows.net'),
            'database' => jam_env('JAM_CLOUD_DB', 'JAM_cloud'),
            'user' => jam_env('JAM_CLOUD_USER'),
            'password' => jam_env('JAM_CLOUD_PASSWORD'),
            'encrypt' => true,
            'role' => 'nube',
            'label' => 'Azure SQL',
            'id_store' => null,
            'mirror' => [
                'server' => jam_env('JAM_CLOUD_MIRROR_SERVER', 'jam-fog.secondary.database.windows.net'),
            ],
        ],
    ];

    /* La nube solo se habilita cuando hay credenciales configuradas. */
    if (empty($nodes['cloud']['user'])) {
        unset($nodes['cloud']);
    }

    /* JAM_ENABLED_NODES limita los nodos disponibles, p. ej. "cloud" en Azure. */
    $enabled = jam_env('JAM_ENABLED_NODES');
    if ($enabled) {
        $names = array_filter(array_map('trim', explode(',', $enabled)));
        $nodes = array_intersect_key($nodes, array_flip($names));
    }

    return $nodes;
}
