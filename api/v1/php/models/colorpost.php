<?php
try {
    $SQL_Query = 'INSERT INTO color (id_color, code, name) VALUES (
        :id_color, 
        :code, 
        :name)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_color', $body['id_color'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ColorId = $body['id_color']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $ColorId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ColorId = 'Error: ' . $ex->getMessage();
};
?>