<?php
try {
    $SQL_Query = 'UPDATE envios SET
        id_venta = :id_venta, 
        direccion_entrega = :direccion_entrega, 
        estado_envio = :estado_envio, 
        numero_guia = :numero_guia 
        WHERE 
        id_envio = :id_envio';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_envio', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_venta', $body['id_venta'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':direccion_entrega', $body['direccion_entrega'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado_envio', $body['estado_envio'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':numero_guia', $body['numero_guia'], PDO::PARAM_STR);
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