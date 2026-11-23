<?php
try {
    $SQL_Query = 'DELETE FROM category
        WHERE
        id_category = :id_category';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_category', $resourceId, PDO::PARAM_INT);
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
