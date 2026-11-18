<?php
try {
    $SQL_Query = 'INSERT INTO message_log (id_store, phone, direction, type, content, response_time_ms, source_node) VALUES (
        :id_store, 
        :phone, 
        :direction, 
        :type, 
        :content, 
        :response_time_ms, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direction', $body['direction'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':type', $body['type'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':content', $body['content'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':response_time_ms', $body['response_time_ms'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $MessageLogId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>