<?php
try {
    $SQL_Query = 'UPDATE inventory SET
        id_store = :id_store, 
        id_variant = :id_variant, 
        stock = :stock, 
        min_stock = :min_stock, 
        source_node = :source_node 
        WHERE 
        id_inventory = :id_inventory';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_inventory', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_variant', $body['id_variant'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':stock', $body['stock'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':min_stock', $body['min_stock'], PDO::PARAM_INT);
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