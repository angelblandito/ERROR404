<?php
try {
    $SQL_Query = 'INSERT INTO category (id_category, code, name, department) VALUES (
        :id_category, 
        :code, 
        :name, 
        :department)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_category', $body['id_category'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':department', $body['department'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $CategoryId = $body['id_category']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>