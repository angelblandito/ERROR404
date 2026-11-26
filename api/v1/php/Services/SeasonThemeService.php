<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use PDO;

/**
 * Seasons: GET /season_theme/current?date=YYYY-MM-DD
 * Returns the season in effect for the client's date (or the server's UTC date).
 * Campaigns have a higher priority than the seasons of the year; a range such as
 * 12/21 to 03/19 crosses the end of the year. Layer: services. No HTTP.
 */
final class SeasonThemeService
{
    public static function current(?string $date): array
    {
        $date = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : gmdate('Y-m-d');
        $monthDay = (int)substr($date, 5, 2) * 100 + (int)substr($date, 8, 2);

        $stmt = StoreService::db()->prepare('SELECT TOP (1) id_theme, code, name, theme_type, color_primary, color_secondary,
                   color_accent, color_accent_text, hero_title, hero_text, effect
            FROM season_theme
            CROSS APPLY (SELECT CAST(:month_day AS INT) AS md,
                                start_month * 100 + start_day AS start_md,
                                end_month * 100 + end_day AS end_md) AS r
            WHERE active = 1
              AND ((r.start_md <= r.end_md AND r.md BETWEEN r.start_md AND r.end_md)
                OR (r.start_md > r.end_md AND (r.md >= r.start_md OR r.md <= r.end_md)))
            ORDER BY priority DESC, id_theme');
        $stmt->bindValue(':month_day', $monthDay, PDO::PARAM_INT);
        $stmt->execute();

        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$theme) {
            throw new AppError('No hay una temporada configurada para esa fecha.', 404);
        }
        return $theme;
    }
}
