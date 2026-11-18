<?php
try {
    $SQL_Query = 'UPDATE order_item SET
        id_order = :id_order, 
        id_variant = :id_variant, 
        quantity = :quantity, 
        unit_price = :unit_price, 
        source_node = :source_node 
        WHERE 
        deleted = 0 AND 
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
        $DB_Error = api_error(404, 'El registro no existe o fue eliminado.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>