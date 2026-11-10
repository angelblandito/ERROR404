<?php
try {
    $SQL_Query = 'UPDATE proveedores SET
        nombre_empresa = :nombre_empresa, 
        contacto = :contacto, 
        telefono = :telefono 
        WHERE 
        id_proveedor = :id_proveedor';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_proveedor', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre_empresa', $body['nombre_empresa'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':contacto', $body['contacto'], PDO::PARAM_STR);
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