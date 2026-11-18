<?php
try {
    $SQL_Query = 'UPDATE product_variant SET
        id_product = :id_product, 
        id_size = :id_size, 
        id_color = :id_color, 
        sku = :sku, 
        source_node = :source_node 
        WHERE 
        id_variant = :id_variant';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_variant', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_product', $body['id_product'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_size', $body['id_size'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_color', $body['id_color'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':sku', $body['sku'], PDO::PARAM_STR);
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