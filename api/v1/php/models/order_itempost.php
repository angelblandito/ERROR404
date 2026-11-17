<?php
try {
    $SQL_Query = 'INSERT INTO order_item (id_order, id_variant, quantity, unit_price, source_node) VALUES (
        :id_order, 
        :id_variant, 
        :quantity, 
        :unit_price, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_order', $body['id_order'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_variant', $body['id_variant'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':quantity', $body['quantity'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':unit_price', $body['unit_price'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $OrderItemId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $OrderItemId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $OrderItemId = 'Error: ' . $ex->getMessage();
};
?>