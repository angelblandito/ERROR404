<?php
try {
    $SQL_Query = 'UPDATE customer SET
        id_loyalty_level = :id_loyalty_level, 
        id_store = :id_store, 
        id_address = :id_address, 
        first_name = :first_name, 
        last_name_paternal = :last_name_paternal, 
        last_name_maternal = :last_name_maternal, 
        birth_date = :birth_date, 
        sex = :sex, 
        phone = :phone, 
        email = :email, 
        accepts_marketing = :accepts_marketing, 
        source_node = :source_node 
        WHERE 
        deleted = 0 AND 
        id_customer = :id_customer';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_customer', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_loyalty_level', $body['id_loyalty_level'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_address', $body['id_address'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':first_name', $body['first_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_paternal', $body['last_name_paternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_maternal', $body['last_name_maternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':birth_date', $body['birth_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':sex', $body['sex'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':accepts_marketing', $body['accepts_marketing'], PDO::PARAM_INT);
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