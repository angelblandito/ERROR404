<?php
try {
    $SQL_Query = 'UPDATE sucursales SET
        nombre = :nombre, 
        tipo = :tipo, 
        direccion = :direccion, 
        telefono = :telefono 
        WHERE 
        id_sucursal = :id_sucursal';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_sucursal', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':tipo', $body['tipo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direccion', $body['direccion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':telefono', $body['telefono'], PDO::PARAM_STR);
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