<?php
try {
    $SQL_Query = 'INSERT INTO product (id_category, name, description, target_gender, base_price, age_segment, source_node) VALUES (
        :id_category, 
        :name, 
        :description, 
        :target_gender, 
        :base_price, 
        :age_segment, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_category', $body['id_category'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':description', $body['description'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':target_gender', $body['target_gender'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':base_price', $body['base_price'], PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':age_segment', $body['age_segment'] ?? 'all', PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ProductId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>