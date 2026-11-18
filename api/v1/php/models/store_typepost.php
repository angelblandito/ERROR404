<?php
try {
    $SQL_Query = 'INSERT INTO store_type (id_store_type, code, name) VALUES (
        :id_store_type, 
        :code, 
        :name)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store_type', $body['id_store_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $StoreTypeId = $body['id_store_type']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $StoreTypeId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $StoreTypeId = 'Error: ' . $ex->getMessage();
};
?>