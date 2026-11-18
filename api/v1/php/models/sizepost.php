<?php
try {
    $SQL_Query = 'INSERT INTO size (id_size, code, name) VALUES (
        :id_size, 
        :code, 
        :name)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_size', $body['id_size'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $SizeId = $body['id_size']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $SizeId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $SizeId = 'Error: ' . $ex->getMessage();
};
?>