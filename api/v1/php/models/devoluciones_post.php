<?php
try {
    $SQL_Query = 'INSERT INTO Devoluciones VALUES (
        :venta_id, 
        :cliente_id, 
        :motivo, 
        :monto_reembolsado, 
        :estado_devolucion, 
        :fecha_solicitud)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':venta_id', $body['venta_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':cliente_id', $body['cliente_id'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':motivo', $body['motivo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':monto_reembolsado', $body['monto_reembolsado'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':estado_devolucion', $body['estado_devolucion'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_solicitud', $body['fecha_solicitud'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $DevolucionId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DevolucionId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $DevolucionId = 'Error: ' . $ex->getMessage();
};
?>