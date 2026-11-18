<?php
try {
    $SQL_Query = 'UPDATE verification_code SET
        id_employee = :id_employee, 
        purpose = :purpose, 
        code_hash = :code_hash, 
        expires_at = :expires_at, 
        attempts = :attempts, 
        used = :used, 
        source_node = :source_node 
        WHERE 
        id_code = :id_code';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_code', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':purpose', $body['purpose'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':code_hash', $body['code_hash'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':expires_at', $body['expires_at'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':attempts', $body['attempts'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':used', $body['used'], PDO::PARAM_INT);
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