<?php
try {
    $SQL_Query = 'UPDATE clientes SET
        nombre_completo = :nombre_completo, 
        email = :email, 
        telefono = :telefono, 
        direccion_default = :direccion_default 
        WHERE 
        id_cliente = :id_cliente';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_cliente', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre_completo', $body['nombre_completo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':telefono', $body['telefono'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':direccion_default', $body['direccion_default'], PDO::PARAM_STR);
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