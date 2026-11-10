<?php
try {
    $SQL_Query = 'INSERT INTO inventario_sucursales VALUES (
        :id_sucursal, 
        :id_producto, 
        :stock)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_producto', $body['id_producto'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':stock', $body['stock'], PDO::PARAM_INT);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $InventarioId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $InventarioId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $InventarioId = 'Error: ' . $ex->getMessage();
};
?>