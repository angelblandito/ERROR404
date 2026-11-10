<?php
try {
    $SQL_Query = 'UPDATE inventario_sucursales SET
        id_sucursal = :id_sucursal, 
        id_producto = :id_producto, 
        stock = :stock 
        WHERE 
        id_inventario = :id_inventario';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_inventario', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_producto', $body['id_producto'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':stock', $body['stock'], PDO::PARAM_INT);
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