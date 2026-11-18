<?php
try {
    $SQL_Query = 'UPDATE loyalty_level SET
        code = :code, 
        name = :name, 
        discount_percent = :discount_percent, 
        min_spent = :min_spent 
        WHERE 
        id_loyalty_level = :id_loyalty_level';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_loyalty_level', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':discount_percent', $body['discount_percent'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':min_spent', $body['min_spent'], PDO::PARAM_STR);
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