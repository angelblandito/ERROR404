<?php
/*
 * GET /season_theme/current?date=AAAA-MM-DD
 * Devuelve la temporada vigente para la fecha del cliente (o la fecha UTC del servidor).
 * Las campañas tienen mayor prioridad que las estaciones; un rango como
 * 12/21 a 03/19 cruza el fin de año.
 */
try {
    $Date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : gmdate('Y-m-d');
    $MonthDay = (int)substr($Date, 5, 2) * 100 + (int)substr($Date, 8, 2);

    $SQL_Query = 'SELECT TOP (1) id_theme, code, name, theme_type, color_primary, color_secondary,
               color_accent, color_accent_text, hero_title, hero_text
        FROM season_theme
        CROSS APPLY (SELECT CAST(:month_day AS INT) AS md,
                            start_month * 100 + start_day AS start_md,
                            end_month * 100 + end_day AS end_md) AS r
        WHERE active = 1
          AND ((r.start_md <= r.end_md AND r.md BETWEEN r.start_md AND r.end_md)
            OR (r.start_md > r.end_md AND (r.md >= r.start_md OR r.md <= r.end_md)))
        ORDER BY priority DESC, id_theme';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindValue(':month_day', $MonthDay, PDO::PARAM_INT);
    $SQL_Sentence->execute();

    $Elements = $SQL_Sentence->fetch(PDO::FETCH_ASSOC);
    if (!$Elements) {
        $DB_Error = api_error(404, 'No hay una temporada configurada para esa fecha.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>