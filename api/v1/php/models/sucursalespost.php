<?php
try {
    $SQL_Query = 'INSERT INTO sucursales VALUES (
        :nombre, 
        :tipo, 
        :direccion, 
        :telefono)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':tipo', $body['tipo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direccion', $body['direccion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':telefono', $body['telefono'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $SucursalId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $SucursalId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $SucursalId = 'Error: ' . $ex->getMessage();
};
?>