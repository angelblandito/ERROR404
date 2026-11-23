<?php
/*
 * Visitas, búsquedas y descargas de la app.
 *
 * Públicos (los envía la tienda sin sesión):
 *   POST /analytics/ping     señal de "sigo aquí" cada 45 s y en cada cambio de página
 *   POST /analytics/leave    el visitante cerró la pestaña o la app
 *   POST /analytics/search   término buscado y cuántos productos encontró
 *   POST /analytics/install  la app se instaló (o se abrió instalada por primera vez)
 *
 * Solo administrador:
 *   GET  /admin/analytics          conectados, visitas por periodo, búsquedas y descargas
 *   GET  /admin/analytics/online   solo los conectados (el panel lo consulta cada 15 s)
 */

const ANALYTICS_ONLINE_SECONDS = 90;
const ANALYTICS_VISIT_GAP_MINUTES = 30;
const ANALYTICS_TOP_SEARCHES = 10;
const ANALYTICS_CHANNELS = ['web', 'app'];
const ANALYTICS_DEVICES = ['movil', 'tableta', 'escritorio'];
const ANALYTICS_PLATFORMS = ['android', 'ios', 'windows', 'mac', 'linux', 'otro'];

function analytics_visitor(array $body): string
{
    $id = strtolower(trim((string)($body['visitor'] ?? '')));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
        throw new StoreHttpException(400, 'Identificador de visitante no válido.');
    }
    return $id;
}

function analytics_choice(array $body, string $key, array $allowed, string $default): string
{
    $value = (string)($body[$key] ?? '');
    return in_array($value, $allowed, true) ? $value : $default;
}

/* Rutas sin parámetros ni números: /producto/12 se guarda como /producto. */
function analytics_path($value): string
{
    $path = strtolower(trim((string)$value));
    $path = preg_replace('#\?.*$#', '', $path);
    $path = preg_replace('#/\d+(?=/|$)#', '', $path);
    if (!preg_match('#^/[a-z0-9/_-]{0,118}$#', $path)) {
        return '/';
    }
    return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
}

function analytics_ping(PDO $pdo, array $body): array
{
    $visitor = analytics_visitor($body);
    $channel = analytics_choice($body, 'channel', ANALYTICS_CHANNELS, 'web');
    $device = analytics_choice($body, 'device', ANALYTICS_DEVICES, 'escritorio');
    $path = analytics_path($body['path'] ?? '/');
    $newPage = !empty($body['view']) ? 1 : 0;
    $customer = store_bearer_token() !== null ? store_current_customer($pdo) : null;

    $open = store_rows($pdo, '
        SELECT TOP (1) id_visit
        FROM dbo.site_visit
        WHERE visitor_id = :v
          AND last_seen >= DATEADD(MINUTE, -' . ANALYTICS_VISIT_GAP_MINUTES . ', SYSUTCDATETIME())
        ORDER BY id_visit DESC', [':v' => $visitor]);

    if ($open) {
        $pdo->prepare('
            UPDATE dbo.site_visit
            SET last_seen = SYSUTCDATETIME(),
                current_path = :p,
                channel = :c,
                device = :d,
                page_views = page_views + :n,
                id_customer = COALESCE(:cu, id_customer)
            WHERE id_visit = :id')
            ->execute([':p' => $path, ':c' => $channel, ':d' => $device, ':n' => $newPage,
                       ':cu' => $customer, ':id' => (int)$open[0]['id_visit']]);
        return ['visit' => (int)$open[0]['id_visit'], 'new_visit' => false];
    }

    $pdo->prepare('
        INSERT INTO dbo.site_visit (visitor_id, channel, device, id_customer, landing_path, current_path)
        VALUES (:v, :c, :d, :cu, :p, :p2)')
        ->execute([':v' => $visitor, ':c' => $channel, ':d' => $device, ':cu' => $customer, ':p' => $path, ':p2' => $path]);
    return ['visit' => (int)$pdo->query('SELECT CAST(SCOPE_IDENTITY() AS BIGINT)')->fetchColumn(), 'new_visit' => true];
}

/* Al cerrar, la visita deja de contar como conectada sin esperar los 90 s. */
function analytics_leave(PDO $pdo, array $body): array
{
    $visitor = analytics_visitor($body);
    $pdo->prepare('
        UPDATE dbo.site_visit
        SET last_seen = CASE WHEN DATEADD(SECOND, -' . ANALYTICS_ONLINE_SECONDS . ', SYSUTCDATETIME()) > started_at
                             THEN DATEADD(SECOND, -' . ANALYTICS_ONLINE_SECONDS . ', SYSUTCDATETIME())
                             ELSE started_at END
        WHERE visitor_id = :v
          AND last_seen >= DATEADD(SECOND, -' . ANALYTICS_ONLINE_SECONDS . ', SYSUTCDATETIME())')
        ->execute([':v' => $visitor]);
    return ['left' => true];
}

function analytics_search(PDO $pdo, array $body): array
{
    $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)($body['term'] ?? ''))), 'UTF-8');
    $term = mb_substr($term, 0, 80, 'UTF-8');
    if (mb_strlen($term, 'UTF-8') < 2) {
        throw new StoreHttpException(400, 'El término de búsqueda es muy corto.');
    }
    $results = max(0, min(100000, (int)($body['results'] ?? 0)));
    $visitor = null;
    try {
        $visitor = analytics_visitor($body);
    } catch (StoreHttpException $e) {
        $visitor = null;
    }

    $pdo->prepare('INSERT INTO dbo.search_log (visitor_id, channel, term, results) VALUES (:v, :c, :t, :r)')
        ->execute([':v' => $visitor, ':c' => analytics_choice($body, 'channel', ANALYTICS_CHANNELS, 'web'),
                   ':t' => $term, ':r' => $results]);
    return ['term' => $term];
}

function analytics_install(PDO $pdo, array $body): array
{
    $visitor = analytics_visitor($body);
    $source = analytics_choice($body, 'source', ['instalacion', 'primer_uso'], 'instalacion');
    $stmt = $pdo->prepare('
        INSERT INTO dbo.app_install (visitor_id, device, platform, source)
        SELECT :v, :d, :p, :s
        WHERE NOT EXISTS (SELECT 1 FROM dbo.app_install WHERE visitor_id = :v2)');
    $stmt->execute([':v' => $visitor, ':d' => analytics_choice($body, 'device', ANALYTICS_DEVICES, 'movil'),
                    ':p' => analytics_choice($body, 'platform', ANALYTICS_PLATFORMS, 'otro'),
                    ':s' => $source, ':v2' => $visitor]);
    return ['counted' => $stmt->rowCount() > 0];
}

/* ---------- Reporte para el administrador ---------- */

/* Expresiones de fecha local: hoy y el inicio de la semana (lunes), el mes y el año. */
function analytics_local(string $column): string
{
    return "CAST($column AT TIME ZONE 'UTC' AT TIME ZONE '" . ADMIN_SQL_TIME_ZONE . "' AS DATE)";
}

function analytics_period_starts(): array
{
    $today = analytics_local('SYSUTCDATETIME()');
    return [
        'day' => $today,
        'week' => "DATEADD(DAY, -((DATEPART(WEEKDAY, $today) + @@DATEFIRST - 2) % 7), $today)",
        'month' => "DATEFROMPARTS(YEAR($today), MONTH($today), 1)",
        'year' => "DATEFROMPARTS(YEAR($today), 1, 1)",
    ];
}

function analytics_online(PDO $pdo): array
{
    $recent = 'last_seen >= DATEADD(SECOND, -' . ANALYTICS_ONLINE_SECONDS . ', SYSUTCDATETIME())';

    $rows = store_rows($pdo, "
        SELECT channel, device, COUNT(DISTINCT visitor_id) AS visitors,
               COUNT(DISTINCT CASE WHEN id_customer IS NOT NULL THEN visitor_id END) AS customers
        FROM dbo.site_visit
        WHERE $recent
        GROUP BY channel, device");

    $online = ['total' => 0, 'web' => 0, 'app' => 0, 'customers' => 0,
               'devices' => array_fill_keys(ANALYTICS_DEVICES, ['web' => 0, 'app' => 0])];
    foreach ($rows as $r) {
        $n = (int)$r['visitors'];
        $online['total'] += $n;
        $online[$r['channel']] += $n;
        $online['customers'] += (int)$r['customers'];
        $online['devices'][$r['device']][$r['channel']] += $n;
    }

    $online['pages'] = array_map(function ($r) {
        return ['path' => $r['current_path'], 'visitors' => (int)$r['visitors']];
    }, store_rows($pdo, "
        SELECT TOP (6) ISNULL(current_path, N'/') AS current_path, COUNT(DISTINCT visitor_id) AS visitors
        FROM dbo.site_visit
        WHERE $recent
        GROUP BY ISNULL(current_path, N'/')
        ORDER BY visitors DESC, current_path"));

    $online['window_seconds'] = ANALYTICS_ONLINE_SECONDS;
    $online['checked_at'] = gmdate('Y-m-d H:i:s');
    return $online;
}

function analytics_visits(PDO $pdo): array
{
    $starts = analytics_period_starts();
    $day = analytics_local('started_at');

    $periods = [];
    foreach (array_keys($starts) as $p) {
        $periods[$p] = ['web' => 0, 'app' => 0, 'total' => 0];
    }
    $rows = store_rows($pdo, "
        SELECT channel,
               SUM(CASE WHEN $day >= {$starts['day']} THEN 1 ELSE 0 END) AS [day],
               SUM(CASE WHEN $day >= {$starts['week']} THEN 1 ELSE 0 END) AS [week],
               SUM(CASE WHEN $day >= {$starts['month']} THEN 1 ELSE 0 END) AS [month],
               COUNT(*) AS [year]
        FROM dbo.site_visit
        WHERE started_at >= DATEADD(DAY, -2, CAST({$starts['year']} AS DATETIME2))
          AND $day >= {$starts['year']}
        GROUP BY channel");
    foreach ($rows as $r) {
        foreach (array_keys($periods) as $p) {
            $periods[$p][$r['channel']] = (int)$r[$p];
            $periods[$p]['total'] += (int)$r[$p];
        }
    }

    $daily = [];
    $today = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    for ($i = ADMIN_CHART_DAYS - 1; $i >= 0; $i--) {
        $daily[$today->modify("-$i day")->format('Y-m-d')] = ['web' => 0, 'app' => 0];
    }
    foreach (store_rows($pdo, "
        SELECT CONVERT(CHAR(10), $day, 23) AS d, channel, COUNT(*) AS visits
        FROM dbo.site_visit
        WHERE started_at >= DATEADD(DAY, -" . (ADMIN_CHART_DAYS + 1) . ", SYSUTCDATETIME())
          AND $day >= DATEADD(DAY, -" . (ADMIN_CHART_DAYS - 1) . ", {$starts['day']})
        GROUP BY $day, channel") as $r) {
        if (isset($daily[$r['d']])) {
            $daily[$r['d']][$r['channel']] = (int)$r['visits'];
        }
    }

    $monthly = [];
    $firstMonth = $today->modify('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $monthly[$firstMonth->modify("-$i month")->format('Y-m')] = ['web' => 0, 'app' => 0];
    }
    foreach (store_rows($pdo, "
        SELECT CONVERT(CHAR(7), $day, 23) AS m, channel, COUNT(*) AS visits
        FROM dbo.site_visit
        WHERE started_at >= DATEADD(MONTH, -13, SYSUTCDATETIME())
          AND $day >= DATEADD(MONTH, -11, {$starts['month']})
        GROUP BY CONVERT(CHAR(7), $day, 23), channel") as $r) {
        if (isset($monthly[$r['m']])) {
            $monthly[$r['m']][$r['channel']] = (int)$r['visits'];
        }
    }

    $toList = function (array $map, string $key) {
        $list = [];
        foreach ($map as $k => $v) {
            $list[] = [$key => $k, 'web' => $v['web'], 'app' => $v['app'], 'total' => $v['web'] + $v['app']];
        }
        return $list;
    };

    return [
        'periods' => $periods,
        'daily' => $toList($daily, 'date'),
        'monthly' => $toList($monthly, 'month'),
    ];
}

function analytics_searches(PDO $pdo): array
{
    $starts = analytics_period_starts();
    $day = analytics_local('searched_at');
    $result = [];
    foreach ($starts as $period => $start) {
        $where = "searched_at >= DATEADD(DAY, -400, SYSUTCDATETIME()) AND $day >= $start";
        $totals = store_rows($pdo, "
            SELECT COUNT(*) AS searches,
                   SUM(CASE WHEN results = 0 THEN 1 ELSE 0 END) AS without_results,
                   SUM(CASE WHEN channel = 'app' THEN 1 ELSE 0 END) AS from_app
            FROM dbo.search_log WHERE $where")[0];
        $top = store_rows($pdo, "
            SELECT TOP (" . ANALYTICS_TOP_SEARCHES . ") term, COUNT(*) AS times,
                   SUM(CASE WHEN channel = 'web' THEN 1 ELSE 0 END) AS web,
                   SUM(CASE WHEN channel = 'app' THEN 1 ELSE 0 END) AS app,
                   MIN(results) AS min_results
            FROM dbo.search_log WHERE $where
            GROUP BY term
            ORDER BY times DESC, term");
        $missing = store_rows($pdo, "
            SELECT TOP (5) term, COUNT(*) AS times
            FROM dbo.search_log WHERE $where AND results = 0
            GROUP BY term
            ORDER BY times DESC, term");

        $result[$period] = [
            'searches' => (int)$totals['searches'],
            'without_results' => (int)$totals['without_results'],
            'from_app' => (int)$totals['from_app'],
            'top' => array_map(function ($r) {
                return ['term' => $r['term'], 'times' => (int)$r['times'], 'web' => (int)$r['web'],
                        'app' => (int)$r['app'], 'found' => (int)$r['min_results'] > 0];
            }, $top),
            'missing' => array_map(function ($r) {
                return ['term' => $r['term'], 'times' => (int)$r['times']];
            }, $missing),
        ];
    }
    return $result;
}

function analytics_installs(PDO $pdo): array
{
    $starts = analytics_period_starts();
    $day = analytics_local('installed_at');
    $totals = store_rows($pdo, "
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN $day >= {$starts['day']} THEN 1 ELSE 0 END) AS [day],
               SUM(CASE WHEN $day >= {$starts['week']} THEN 1 ELSE 0 END) AS [week],
               SUM(CASE WHEN $day >= {$starts['month']} THEN 1 ELSE 0 END) AS [month],
               SUM(CASE WHEN $day >= {$starts['year']} THEN 1 ELSE 0 END) AS [year]
        FROM dbo.app_install")[0];

    return [
        'total' => (int)$totals['total'],
        'periods' => ['day' => (int)$totals['day'], 'week' => (int)$totals['week'],
                      'month' => (int)$totals['month'], 'year' => (int)$totals['year']],
        'platforms' => array_map(function ($r) {
            return ['platform' => $r['platform'], 'installs' => (int)$r['installs']];
        }, store_rows($pdo, '
            SELECT platform, COUNT(*) AS installs FROM dbo.app_install
            GROUP BY platform ORDER BY installs DESC, platform')),
    ];
}

function analytics_report(PDO $pdo): array
{
    return [
        'online' => analytics_online($pdo),
        'visits' => analytics_visits($pdo),
        'searches' => analytics_searches($pdo),
        'installs' => analytics_installs($pdo),
        'time_zone' => ADMIN_SQL_TIME_ZONE,
    ];
}

function analytics_handle(string $method, string $uri, array $body): bool
{
    $public = $method === 'POST' && strpos($uri, '/analytics/') === 0;
    $admin = $method === 'GET' && ($uri === '/admin/analytics' || $uri === '/admin/analytics/online');
    if (!$public && !$admin) {
        return false;
    }

    store_run($uri, function () use ($uri, $body, $admin) {
        $pdo = store_db();
        if ($admin) {
            store_require_admin($pdo);
            if ($uri === '/admin/analytics/online') {
                store_ok(200, analytics_online($pdo), 'Conectados');
            } else {
                store_ok(200, analytics_report($pdo), 'Visitas');
            }
        } elseif ($uri === '/analytics/ping') {
            store_ok(200, analytics_ping($pdo, $body), 'Visita registrada');
        } elseif ($uri === '/analytics/leave') {
            store_ok(200, analytics_leave($pdo, $body), 'Visita cerrada');
        } elseif ($uri === '/analytics/search') {
            store_ok(201, analytics_search($pdo, $body), 'Búsqueda registrada');
        } elseif ($uri === '/analytics/install') {
            store_ok(201, analytics_install($pdo, $body), 'Instalación registrada');
        } else {
            throw new StoreHttpException(404, 'Ruta no encontrada.');
        }
    });
    return true;
}
