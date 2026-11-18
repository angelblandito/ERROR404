<?php
try {
    $SQL_Query = 'UPDATE employee_absence SET
        id_employee = :id_employee, 
        id_absence_type = :id_absence_type, 
        absence_date = :absence_date, 
        source_node = :source_node 
        WHERE 
        deleted = 0 AND 
        id_absence = :id_absence';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_absence', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_absence_type', $body['id_absence_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':absence_date', $body['absence_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $newData = $body;
    } else {
        $DB_Error = api_error(404, 'El registro no existe o fue eliminado.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>