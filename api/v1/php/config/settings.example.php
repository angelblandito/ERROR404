<?php
/*
 * Copiar este archivo como config/settings.php y ajustar los valores.
 * settings.php está en .gitignore: las contraseñas no se suben a GitHub.
 * Las variables de entorno con el mismo nombre tienen prioridad.
 */
return [
    /* Instancia local de SQL Server (Developer recomendada para replicación). */
    'JAM_LOCAL_SERVER' => 'localhost',
    /* Vacío = autenticación de Windows. */
    'JAM_LOCAL_USER' => '',
    'JAM_LOCAL_PASSWORD' => '',

    /* Nodo que usan los endpoints CRUD (/product, /customer_order, ...). */
    'JAM_DEFAULT_NODE' => 'central',

    /* Nodos habilitados, separados por coma. Vacío = todos. */
    'JAM_ENABLED_NODES' => '',

    /* '1' = solo lectura (POST, PUT y DELETE responden 405). Se usa en Azure. */
    'JAM_READ_ONLY' => '0',

    /* Azure SQL (listener del grupo de conmutación por error).
       Con usuario y contraseña, el nodo cloud se activa: la API copia JAM_db
       a JAM_cloud después de cada escritura y lo usa como espejo cuando una
       sucursal no responde. */
    'JAM_CLOUD_SERVER' => 'jam-fog.database.windows.net',
    'JAM_CLOUD_MIRROR_SERVER' => 'jam-fog.secondary.database.windows.net',
    'JAM_CLOUD_DB' => 'JAM_cloud',
    'JAM_CLOUD_USER' => '',
    'JAM_CLOUD_PASSWORD' => '',
    /* Segundos de espera al conectar con Azure, y segundos que se deja de
       intentar después de un fallo (para no esperar en cada petición). */
    'JAM_CLOUD_TIMEOUT' => '4',
    'JAM_CLOUD_RETRY_AFTER' => '30',
];
