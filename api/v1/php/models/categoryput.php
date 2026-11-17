<?php
try {
    $SQL_Query = 'UPDATE category SET
        code = :code, 
        name = :name 
        WHERE 
        id_category = :id_category';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_category', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
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