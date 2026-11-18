<?php
try {
    $SQL_Query = 'UPDATE promotion SET
        code = :code, 
        description = :description, 
        discount_percent = :discount_percent, 
        start_date = :start_date, 
        end_date = :end_date, 
        source_node = :source_node 
        WHERE 
        id_promotion = :id_promotion';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_promotion', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':description', $body['description'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':discount_percent', $body['discount_percent'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':start_date', $body['start_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':end_date', $body['end_date'], PDO::PARAM_STR);
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