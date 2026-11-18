<?php
try {
    [$Offset, $PageSize] = api_page();

    $SQL_Query = 'SELECT id_theme, code, name, theme_type, start_month, start_day, end_month, end_day, priority,
               color_primary, color_secondary, color_accent, color_accent_text, hero_title, hero_text, effect, active
        FROM season_theme
        ORDER BY priority DESC, id_theme
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindValue(':offset', $Offset, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':page_size', $PageSize, PDO::PARAM_INT);
    $SQL_Sentence->execute();

    $Elements = $SQL_Sentence->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>