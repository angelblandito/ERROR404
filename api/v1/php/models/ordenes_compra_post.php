<?php
try {
    $SQL_Query = 'INSERT INTO Ordenes_Compra VALUES (
        :proveedor_id, 
        :fecha_orden, 
        :total_orden, 
        :estado)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':proveedor_id', $body['proveedor_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':fecha_orden', $body['fecha_orden'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total_orden', $body['total_orden'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado', $body['estado'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $OrdenCompraId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $OrdenCompraId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $OrdenCompraId = 'Error: ' . $ex->getMessage();
};
?>