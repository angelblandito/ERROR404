<?php
try {
    $SQL_Query = 'UPDATE Ordenes_Compra SET
        proveedor_id = :proveedor_id, 
        fecha_orden = :fecha_orden, 
        total_orden = :total_orden, 
        estado = :estado 
        WHERE 
        orden_compra_id = :orden_compra_id';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':orden_compra_id', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':proveedor_id', $body['proveedor_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':fecha_orden', $body['fecha_orden'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total_orden', $body['total_orden'], PDO::PARAM_STR);
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