<?php
try {
    $SQL_Query = 'INSERT INTO metodos_pago VALUES (
        :metodo)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':metodo', $body['metodo'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $MetodoId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $MetodoId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $MetodoId = 'Error: ' . $ex->getMessage();
};
?>