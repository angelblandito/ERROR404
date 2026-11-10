<?php
try {
    $SQL_Query = 'UPDATE categorias SET
        nombre = :nombre, 
        descripcion = :descripcion 
        WHERE 
        id_categoria = :id_categoria';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_categoria', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descripcion', $body['descripcion'], PDO::PARAM_STR);
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