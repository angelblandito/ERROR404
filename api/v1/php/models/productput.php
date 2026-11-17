<?php
try {
    $SQL_Query = 'UPDATE product SET
        id_category = :id_category, 
        name = :name, 
        description = :description, 
        target_gender = :target_gender, 
        base_price = :base_price, 
        source_node = :source_node 
        WHERE 
        id_product = :id_product';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_product', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_category', $body['id_category'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':description', $body['description'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':target_gender', $body['target_gender'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':base_price', $body['base_price'], PDO::PARAM_STR);
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