<?php
try {
    $SQL_Query = 'UPDATE store SET
        id_owner = :id_owner, 
        id_store_type = :id_store_type, 
        id_address = :id_address, 
        store_name = :store_name, 
        tax_id = :tax_id, 
        contact_phone = :contact_phone, 
        email = :email, 
        whatsapp = :whatsapp, 
        opened_at = :opened_at, 
        active = :active, 
        source_node = :source_node 
        WHERE 
        id_store = :id_store';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_owner', $body['id_owner'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store_type', $body['id_store_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_address', $body['id_address'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':store_name', $body['store_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':tax_id', $body['tax_id'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':contact_phone', $body['contact_phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':whatsapp', $body['whatsapp'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':opened_at', $body['opened_at'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':active', $body['active'], PDO::PARAM_INT);
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