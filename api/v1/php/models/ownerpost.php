<?php
try {
    $SQL_Query = 'INSERT INTO owner (first_name, last_name_paternal, last_name_maternal, email, phone, password_hash, active, source_node) VALUES (
        :first_name, 
        :last_name_paternal, 
        :last_name_maternal, 
        :email, 
        :phone, 
        :password_hash, 
        :active, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':first_name', $body['first_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_paternal', $body['last_name_paternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_maternal', $body['last_name_maternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':password_hash', $body['password_hash'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':active', $body['active'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $OwnerId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>