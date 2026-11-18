<?php
try {
    $SQL_Query = 'UPDATE audit_log SET
        id_store = :id_store, 
        id_actor = :id_actor, 
        actor_role = :actor_role, 
        action = :action, 
        entity = :entity, 
        entity_id = :entity_id, 
        detail = :detail, 
        source_node = :source_node 
        WHERE 
        id_audit = :id_audit';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_audit', $resourceId, PDO::PARAM_INT);
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
        $newData = $body;
    } else {
        $DB_Error = api_error(404, 'El registro no existe o fue eliminado.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>