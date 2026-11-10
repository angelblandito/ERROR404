<?php
try {
    $SQL_Query = 'UPDATE Cupones_Descuento SET
        codigo = :codigo, 
        descuento_porcentaje = :descuento_porcentaje, 
        fecha_inicio = :fecha_inicio, 
        fecha_fin = :fecha_fin, 
        activo = :activo 
        WHERE 
        cupon_id = :cupon_id';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':cupon_id', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':codigo', $body['codigo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descuento_porcentaje', $body['descuento_porcentaje'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_inicio', $body['fecha_inicio'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_fin', $body['fecha_fin'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':activo', $body['activo'], PDO::PARAM_INT);
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