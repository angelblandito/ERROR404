<?php
try {
    $SQL_Query = 'UPDATE employee_schedule SET deleted = 1
        WHERE
        deleted = 0 AND
        id_schedule = :id_schedule';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_schedule', $resourceId, PDO::PARAM_INT);
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
