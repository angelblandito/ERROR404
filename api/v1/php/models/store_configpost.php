<?php
try {
    $SQL_Query = 'INSERT INTO store_config (id_store, config_key, config_value, source_node) VALUES (
        :id_store, 
        :config_key, 
        :config_value, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':config_key', $body['config_key'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':config_value', $body['config_value'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $StoreConfigId = $body['id_store'] . '-' . $body['config_key']; // Composite key (no AUTO_INCREMENT)
    } else {
        $StoreConfigId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $StoreConfigId = 'Error: ' . $ex->getMessage();
};
?>