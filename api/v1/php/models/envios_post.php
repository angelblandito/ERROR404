<?php
try {
    $SQL_Query = 'INSERT INTO envios VALUES (
        :id_venta, 
        :direccion_entrega, 
        :estado_envio, 
        :numero_guia)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_venta', $body['id_venta'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':direccion_entrega', $body['direccion_entrega'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado_envio', $body['estado_envio'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':numero_guia', $body['numero_guia'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $EnvioId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $EnvioId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $EnvioId = 'Error: ' . $ex->getMessage();
};
?>