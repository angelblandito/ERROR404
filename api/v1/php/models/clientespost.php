<?php
try {
    $SQL_Query = 'INSERT INTO clientes VALUES (
        :nombre_completo, 
        :email, 
        :telefono, 
        :direccion_default)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':nombre_completo', $body['nombre_completo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':telefono', $body['telefono'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direccion_default', $body['direccion_default'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ClienteId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $ClienteId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ClienteId = 'Error: ' . $ex->getMessage();
};
?>