<?php
try {
    $SQL_Query = 'UPDATE message_log SET
        id_store = :id_store, 
        phone = :phone, 
        direction = :direction, 
        type = :type, 
        content = :content, 
        response_time_ms = :response_time_ms, 
        source_node = :source_node 
        WHERE 
        id_message = :id_message';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_message', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direction', $body['direction'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':type', $body['type'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':content', $body['content'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':response_time_ms', $body['response_time_ms'], PDO::PARAM_INT);
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