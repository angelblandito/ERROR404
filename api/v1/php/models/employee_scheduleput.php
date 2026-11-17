<?php
try {
    $SQL_Query = 'UPDATE employee_schedule SET
        id_employee = :id_employee, 
        weekday = :weekday, 
        start_time = :start_time, 
        end_time = :end_time, 
        source_node = :source_node 
        WHERE 
        id_schedule = :id_schedule';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_schedule', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':weekday', $body['weekday'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':start_time', $body['start_time'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':end_time', $body['end_time'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $newData = $body;
    } else {
        $newData = 'Error: Cannot update record';
    };
}
catch (PDOException $ex) {
    $newData = "Hubo un error!!!!!!!!!!!!";
};
?>