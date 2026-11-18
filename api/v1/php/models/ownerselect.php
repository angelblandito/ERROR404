<?php
try {
    [$Offset, $PageSize] = api_page();

    $SQL_Query = 'SELECT id_owner, first_name, last_name_paternal, last_name_maternal, email, phone, password_hash, active, created_at, source_node, updated_at, deleted
        FROM owner
        WHERE deleted = 0
        ORDER BY id_owner
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