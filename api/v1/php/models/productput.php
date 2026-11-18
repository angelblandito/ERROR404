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
        deleted = 0 AND 
        id_product = :id_product AND 
        (:rv_check IS NULL OR row_version = CAST(CAST(:rv_value AS BIGINT) AS BINARY(8)))';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_product', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':rv_check', $body['row_version'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':rv_value', $body['row_version'] ?? null, PDO::PARAM_STR);
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
        $DB_Error = isset($body['row_version'])
            ? api_error(409, 'El registro fue modificado por otro usuario. Vuelva a cargarlo.')
            : api_error(404, 'El registro no existe o fue eliminado.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>