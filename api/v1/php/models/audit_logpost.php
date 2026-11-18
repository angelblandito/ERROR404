<?php
try {
    $SQL_Query = 'INSERT INTO audit_log (id_store, id_actor, actor_role, action, entity, entity_id, detail, source_node) VALUES (
        :id_store, 
        :id_actor, 
        :actor_role, 
        :action, 
        :entity, 
        :entity_id, 
        :detail, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_actor', $body['id_actor'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':actor_role', $body['actor_role'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':action', $body['action'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':entity', $body['entity'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':entity_id', $body['entity_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':detail', $body['detail'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $AuditLogId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>