<?php
try {
    $SQL_Query = 'INSERT INTO Resenas_Productos VALUES (
        :producto_id, 
        :cliente_id, 
        :calificacion, 
        :comentario, 
        :fecha_resena)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':producto_id', $body['producto_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cliente_id', $body['cliente_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':calificacion', $body['calificacion'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':comentario', $body['comentario'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_resena', $body['fecha_resena'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ResenaId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $ResenaId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ResenaId = 'Error: ' . $ex->getMessage();
};
?>