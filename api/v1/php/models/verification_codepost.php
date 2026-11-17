<?php
try {
    $SQL_Query = 'INSERT INTO verification_code (id_employee, purpose, code_hash, expires_at, attempts, used, source_node) VALUES (
        :id_employee, 
        :purpose, 
        :code_hash, 
        :expires_at, 
        :attempts, 
        :used, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':purpose', $body['purpose'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':code_hash', $body['code_hash'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':expires_at', $body['expires_at'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':attempts', $body['attempts'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':used', $body['used'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $VerificationCodeId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $VerificationCodeId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $VerificationCodeId = 'Error: ' . $ex->getMessage();
};
?>