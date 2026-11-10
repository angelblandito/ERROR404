<?php
try {
    $SQL_Query = 'UPDATE compras_proveedores SET
        id_proveedor = :id_proveedor, 
        id_sucursal = :id_sucursal, 
        fecha_pedido = :fecha_pedido, 
        total_compra = :total_compra, 
        estado = :estado 
        WHERE 
        id_compra = :id_compra';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_compra', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_proveedor', $body['id_proveedor'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':fecha_pedido', $body['fecha_pedido'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total_compra', $body['total_compra'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado', $body['estado'], PDO::PARAM_STR);
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