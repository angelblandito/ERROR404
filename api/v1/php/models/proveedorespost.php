<?php
try {
    $SQL_Query = 'INSERT INTO proveedores VALUES (
        :nombre_empresa, 
        :contacto, 
        :telefono)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':nombre_empresa', $body['nombre_empresa'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':contacto', $body['contacto'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':telefono', $body['telefono'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ProveedorId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $ProveedorId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ProveedorId = 'Error: ' . $ex->getMessage();
};
?>