<?php
try {
    $SQL_Query = 'INSERT INTO state (id_state, code, name) VALUES (
        :id_state, 
        :code, 
        :name)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_state', $body['id_state'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $StateId = $body['id_state']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $StateId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $StateId = 'Error: ' . $ex->getMessage();
};
?>