<?php
try {
    $SQL_Query = 'UPDATE detalles_venta SET
        id_venta = :id_venta, 
        id_producto = :id_producto, 
        cantidad = :cantidad, 
        precio_unitario = :precio_unitario, 
        subtotal = :subtotal 
        WHERE 
        id_detalle = :id_detalle';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_detalle', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_venta', $body['id_venta'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_producto', $body['id_producto'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cantidad', $body['cantidad'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':precio_unitario', $body['precio_unitario'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':subtotal', $body['subtotal'], PDO::PARAM_STR);
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