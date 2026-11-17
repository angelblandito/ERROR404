<?php
try {
    $SQL_Query = 'UPDATE order_item SET
        id_order = :id_order, 
        id_variant = :id_variant, 
        quantity = :quantity, 
        unit_price = :unit_price, 
        source_node = :source_node 
        WHERE 
        id_item = :id_item';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_item', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_order', $body['id_order'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_variant', $body['id_variant'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':quantity', $body['quantity'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':unit_price', $body['unit_price'], PDO::PARAM_STR);
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