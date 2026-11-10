<?php
try {
    $SQL_Query = 'UPDATE Devoluciones SET
        venta_id = :venta_id, 
        cliente_id = :cliente_id, 
        motivo = :motivo, 
        monto_reembolsado = :monto_reembolsado, 
        estado_devolucion = :estado_devolucion, 
        fecha_solicitud = :fecha_solicitud 
        WHERE 
        devolucion_id = :devolucion_id';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':devolucion_id', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':venta_id', $body['venta_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cliente_id', $body['cliente_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':motivo', $body['motivo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':monto_reembolsado', $body['monto_reembolsado'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado_devolucion', $body['estado_devolucion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_solicitud', $body['fecha_solicitud'], PDO::PARAM_STR);
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