<?php
try {
    $SQL_Query = 'INSERT INTO Cupones_Descuento VALUES (
        :codigo, 
        :descuento_porcentaje, 
        :fecha_inicio, 
        :fecha_fin, 
        :activo)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':codigo', $body['codigo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descuento_porcentaje', $body['descuento_porcentaje'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_inicio', $body['fecha_inicio'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_fin', $body['fecha_fin'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':activo', $body['activo'], PDO::PARAM_INT);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $CuponId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $CuponId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $CuponId = 'Error: ' . $ex->getMessage();
};
?>