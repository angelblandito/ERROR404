<?php
try {
    $SQL_Query = 'INSERT INTO detalles_venta VALUES (
        :id_venta, 
        :id_producto, 
        :cantidad, 
        :precio_unitario, 
        :subtotal)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_venta', $body['id_venta'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_producto', $body['id_producto'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cantidad', $body['cantidad'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':precio_unitario', $body['precio_unitario'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':subtotal', $body['subtotal'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $DetalleId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DetalleId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $DetalleId = 'Error: ' . $ex->getMessage();
};
?>