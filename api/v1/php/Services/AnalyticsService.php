<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Visits, searches and app installs.
 *
 * Public (the store sends them without a session):
 *   POST /analytics/ping     "still here" signal every 45 s and on every page change
 *   POST /analytics/leave    the visitor closed the tab or the app
 *   POST /analytics/search   searched term and how many products it found
 *   POST /analytics/install  the app was installed (or opened installed for the first time)
 *
 * Administrador only:
 *   GET  /admin/analytics          online visitors, visits per period, searches and installs
 *   GET  /admin/analytics/online   only the online visitors (the panel asks every 15 s)
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class AnalyticsService
{
    public const ONLINE_SECONDS = 90;

    public const VISIT_GAP_MINUTES = 30;

    public const TOP_SEARCHES = 10;

    public const CHANNELS = ['web', 'app'];

    public const DEVICES = ['movil', 'tableta', 'escritorio'];

    public const PLATFORMS = ['android', 'ios', 'windows', 'mac', 'linux', 'otro'];

    public static function visitor(array $body): string
    {
        $id = strtolower(trim(StoreService::field($body, 'visitor', '')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new AppError('Identificador de visitante no válido.', 400);
        }
        return $id;
    }

    public static function choice(array $body, string $key, array $allowed, string $default): string
    {
        $value = StoreService::field($body, $key, '');
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /* Paths without parameters or numbers: /producto/12 is stored as /producto. */
    public static function path($value): string
    {
        $path = strtolower(trim(is_scalar($value) ? (string)$value : '/'));
        $path = preg_replace('#\?.*$#', '', $path);
        $path = preg_replace('#/\d+(?=/|$)#', '', $path);
        if (!preg_match('#^/[a-z0-9/_-]{0,118}$#', $path)) {
            return '/';
        }
        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }

    public static function ping(PDO $pdo, array $body, ?array $actor = null): array
    {
        $visitor = self::visitor($body);
        $channel = self::choice($body, 'channel', self::CHANNELS, 'web');
        $device = self::choice($body, 'device', self::DEVICES, 'escritorio');
        $path = self::path($body['path'] ?? '/');
        $newPage = !empty($body['view']) ? 1 : 0;
        $customer = $actor !== null ? (int)$actor['id_customer'] : null;

        $open = StoreService::rows($pdo, '
            SELECT TOP (1) id_visit
            FROM dbo.site_visit
            WHERE visitor_id = :v
              AND last_seen >= DATEADD(MINUTE, -' . self::VISIT_GAP_MINUTES . ', SYSUTCDATETIME())
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

    /* On close, the visit stops counting as online without waiting the 90 s. */
    public static function leave(PDO $pdo, array $body): array
    {
        $visitor = self::visitor($body);
        $pdo->prepare('
            UPDATE dbo.site_visit
            SET last_seen = CASE WHEN DATEADD(SECOND, -' . self::ONLINE_SECONDS . ', SYSUTCDATETIME()) > started_at
                                 THEN DATEADD(SECOND, -' . self::ONLINE_SECONDS . ', SYSUTCDATETIME())
                                 ELSE started_at END
            WHERE visitor_id = :v
              AND last_seen >= DATEADD(SECOND, -' . self::ONLINE_SECONDS . ', SYSUTCDATETIME())')
            ->execute([':v' => $visitor]);
        return ['left' => true];
    }

    public static function search(PDO $pdo, array $body): array
    {
        $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', StoreService::field($body, 'term', ''))), 'UTF-8');
        $term = mb_substr($term, 0, 80, 'UTF-8');
        if (mb_strlen($term, 'UTF-8') < 2) {
            throw new AppError('El término de búsqueda es muy corto.', 400);
        }
        $results = max(0, min(100000, (int) StoreService::number($body, 'results', 0)));
        $visitor = null;
        try {
            $visitor = self::visitor($body);
        } catch (AppError $e) {
            $visitor = null;
        }

        $pdo->prepare('INSERT INTO dbo.search_log (visitor_id, channel, term, results) VALUES (:v, :c, :t, :r)')
            ->execute([':v' => $visitor, ':c' => self::choice($body, 'channel', self::CHANNELS, 'web'),
                       ':t' => $term, ':r' => $results]);
        return ['term' => $term];
    }

    public static function install(PDO $pdo, array $body): array
    {
        $visitor = self::visitor($body);
        $source = self::choice($body, 'source', ['instalacion', 'primer_uso'], 'instalacion');
        $stmt = $pdo->prepare('
            INSERT INTO dbo.app_install (visitor_id, device, platform, source)
            SELECT :v, :d, :p, :s
            WHERE NOT EXISTS (SELECT 1 FROM dbo.app_install WHERE visitor_id = :v2)');
        $stmt->execute([':v' => $visitor, ':d' => self::choice($body, 'device', self::DEVICES, 'movil'),
                        ':p' => self::choice($body, 'platform', self::PLATFORMS, 'otro'),
                        ':s' => $source, ':v2' => $visitor]);
        return ['counted' => $stmt->rowCount() > 0];
    }

    /* ---------- Report for the administrator ---------- */
    /* Local date expressions: today and the start of the week (Monday), the month and the year. */
    public static function local(string $column): string
    {
        return "CAST($column AT TIME ZONE 'UTC' AT TIME ZONE '" . AdminService::SQL_TIME_ZONE . "' AS DATE)";
    }

    public static function periodStarts(): array
    {
        $today = self::local('SYSUTCDATETIME()');
        return [
            'day' => $today,
            'week' => "DATEADD(DAY, -((DATEPART(WEEKDAY, $today) + @@DATEFIRST - 2) % 7), $today)",
            'month' => "DATEFROMPARTS(YEAR($today), MONTH($today), 1)",
            'year' => "DATEFROMPARTS(YEAR($today), 1, 1)",
        ];
    }

    public static function online(PDO $pdo): array
    {
        $recent = 'last_seen >= DATEADD(SECOND, -' . self::ONLINE_SECONDS . ', SYSUTCDATETIME())';

        $rows = StoreService::rows($pdo, "
            SELECT channel, device, COUNT(DISTINCT visitor_id) AS visitors,
                   COUNT(DISTINCT CASE WHEN id_customer IS NOT NULL THEN visitor_id END) AS customers
            FROM dbo.site_visit
            WHERE $recent
            GROUP BY channel, device");

        $online = ['total' => 0, 'web' => 0, 'app' => 0, 'customers' => 0,
                   'devices' => array_fill_keys(self::DEVICES, ['web' => 0, 'app' => 0])];
        foreach ($rows as $r) {
            $n = (int)$r['visitors'];
            $online['total'] += $n;
            $online[$r['channel']] += $n;
            $online['customers'] += (int)$r['customers'];
            $online['devices'][$r['device']][$r['channel']] += $n;
        }

        $online['pages'] = array_map(function ($r) {
            return ['path' => $r['current_path'], 'visitors' => (int)$r['visitors']];
        }, StoreService::rows($pdo, "
            SELECT TOP (6) ISNULL(current_path, N'/') AS current_path, COUNT(DISTINCT visitor_id) AS visitors
            FROM dbo.site_visit
            WHERE $recent
            GROUP BY ISNULL(current_path, N'/')
            ORDER BY visitors DESC, current_path"));

        $online['window_seconds'] = self::ONLINE_SECONDS;
        $online['checked_at'] = gmdate('Y-m-d H:i:s');
        return $online;
    }

    public static function visits(PDO $pdo): array
    {
        $starts = self::periodStarts();
        $day = self::local('started_at');

        $periods = [];
        foreach (array_keys($starts) as $p) {
            $periods[$p] = ['web' => 0, 'app' => 0, 'total' => 0];
        }
        $rows = StoreService::rows($pdo, "
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
        for ($i = AdminService::CHART_DAYS - 1; $i >= 0; $i--) {
            $daily[$today->modify("-$i day")->format('Y-m-d')] = ['web' => 0, 'app' => 0];
        }
        foreach (StoreService::rows($pdo, "
            SELECT CONVERT(CHAR(10), $day, 23) AS d, channel, COUNT(*) AS visits
            FROM dbo.site_visit
            WHERE started_at >= DATEADD(DAY, -" . (AdminService::CHART_DAYS + 1) . ", SYSUTCDATETIME())
              AND $day >= DATEADD(DAY, -" . (AdminService::CHART_DAYS - 1) . ", {$starts['day']})
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
        foreach (StoreService::rows($pdo, "
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

    public static function searches(PDO $pdo): array
    {
        $starts = self::periodStarts();
        $day = self::local('searched_at');
        $result = [];
        foreach ($starts as $period => $start) {
            $where = "searched_at >= DATEADD(DAY, -400, SYSUTCDATETIME()) AND $day >= $start";
            $totals = StoreService::rows($pdo, "
                SELECT COUNT(*) AS searches,
                       SUM(CASE WHEN results = 0 THEN 1 ELSE 0 END) AS without_results,
                       SUM(CASE WHEN channel = 'app' THEN 1 ELSE 0 END) AS from_app
                FROM dbo.search_log WHERE $where")[0];
            $top = StoreService::rows($pdo, "
                SELECT TOP (" . self::TOP_SEARCHES . ") term, COUNT(*) AS times,
                       SUM(CASE WHEN channel = 'web' THEN 1 ELSE 0 END) AS web,
                       SUM(CASE WHEN channel = 'app' THEN 1 ELSE 0 END) AS app,
                       MIN(results) AS min_results
                FROM dbo.search_log WHERE $where
                GROUP BY term
                ORDER BY times DESC, term");
            $missing = StoreService::rows($pdo, "
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

    public static function installs(PDO $pdo): array
    {
        $starts = self::periodStarts();
        $day = self::local('installed_at');
        $totals = StoreService::rows($pdo, "
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
            }, StoreService::rows($pdo, '
                SELECT platform, COUNT(*) AS installs FROM dbo.app_install
                GROUP BY platform ORDER BY installs DESC, platform')),
        ];
    }

    public static function report(PDO $pdo): array
    {
        return [
            'online' => self::online($pdo),
            'visits' => self::visits($pdo),
            'searches' => self::searches($pdo),
            'installs' => self::installs($pdo),
            'time_zone' => AdminService::SQL_TIME_ZONE,
        ];
    }
}
