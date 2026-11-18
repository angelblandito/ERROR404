<?php
try {
    $SQL_Query = 'INSERT INTO product_variant (id_product, id_size, id_color, sku, source_node) VALUES (
        :id_product, 
        :id_size, 
        :id_color, 
        :sku, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_product', $body['id_product'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_size', $body['id_size'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_color', $body['id_color'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':sku', $body['sku'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ProductVariantId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $ProductVariantId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ProductVariantId = 'Error: ' . $ex->getMessage();
};
?>