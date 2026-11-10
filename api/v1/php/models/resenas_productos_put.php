<?php
try {
    $SQL_Query = 'UPDATE Resenas_Productos SET
        producto_id = :producto_id, 
        cliente_id = :cliente_id, 
        calificacion = :calificacion, 
        comentario = :comentario, 
        fecha_resena = :fecha_resena 
        WHERE 
        resena_id = :resena_id';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':resena_id', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':producto_id', $body['producto_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cliente_id', $body['cliente_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':calificacion', $body['calificacion'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':comentario', $body['comentario'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_resena', $body['fecha_resena'], PDO::PARAM_STR);
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