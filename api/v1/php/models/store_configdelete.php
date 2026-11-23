<?php
try {
    $SQL_Query = 'UPDATE store_config SET deleted = 1
        WHERE
        deleted = 0 AND
        id_store = :id_store AND
        config_key = :config_key';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':config_key', $subResourceId, PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $newData = ['deleted' => true];
    } else {
        $DB_Error = api_error(404, 'El registro no existe o ya fue eliminado.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
