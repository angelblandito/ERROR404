<?php
try {
    $SQL_Query = 'UPDATE productos SET
        id_categoria = :id_categoria, 
        id_proveedor = :id_proveedor, 
        codigo_sku = :codigo_sku, 
        nombre = :nombre, 
        descripcion = :descripcion, 
        precio_venta = :precio_venta 
        WHERE 
        id_producto = :id_producto';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_producto', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_categoria', $body['id_categoria'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_proveedor', $body['id_proveedor'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':codigo_sku', $body['codigo_sku'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descripcion', $body['descripcion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':precio_venta', $body['precio_venta'], PDO::PARAM_STR);
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