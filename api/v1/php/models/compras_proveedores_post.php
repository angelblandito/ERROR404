<?php
try {
    $SQL_Query = 'INSERT INTO compras_proveedores VALUES (
        :id_proveedor, 
        :id_sucursal, 
        :fecha_pedido, 
        :total_compra, 
        :estado)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_proveedor', $body['id_proveedor'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':fecha_pedido', $body['fecha_pedido'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total_compra', $body['total_compra'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado', $body['estado'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $CompraId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $CompraId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $CompraId = 'Error: ' . $ex->getMessage();
};
?>