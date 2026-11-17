<?php
try {
    $SQL_Query = 'UPDATE store_config SET
        config_value = :config_value, 
        source_node = :source_node 
        WHERE 
        id_store = :id_store AND 
        config_key = :config_key';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':config_key', $subResourceId, PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':config_value', $body['config_value'], PDO::PARAM_STR);
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