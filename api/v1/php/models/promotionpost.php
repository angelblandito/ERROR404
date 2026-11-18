<?php
try {
    $SQL_Query = 'INSERT INTO promotion (code, description, discount_percent, start_date, end_date, source_node) VALUES (
        :code, 
        :description, 
        :discount_percent, 
        :start_date, 
        :end_date, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':description', $body['description'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':discount_percent', $body['discount_percent'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':start_date', $body['start_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':end_date', $body['end_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $PromotionId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>