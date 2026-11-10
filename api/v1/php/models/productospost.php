<?php
try {
    $SQL_Query = 'INSERT INTO productos VALUES (
        :id_categoria, 
        :id_proveedor, 
        :codigo_sku, 
        :nombre, 
        :descripcion, 
        :precio_venta)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_categoria', $body['id_categoria'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_proveedor', $body['id_proveedor'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':codigo_sku', $body['codigo_sku'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descripcion', $body['descripcion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':precio_venta', $body['precio_venta'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $ProductoId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $ProductoId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $ProductoId = 'Error: ' . $ex->getMessage();
};
?>