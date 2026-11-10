<?php
try {
    $SQL_Query = 'UPDATE ventas SET
        id_sucursal = :id_sucursal, 
        id_cliente = :id_cliente, 
        id_empleado = :id_empleado, 
        id_metodo = :id_metodo, 
        fecha_venta = :fecha_venta, 
        total = :total 
        WHERE 
        id_venta = :id_venta';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_venta', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_cliente', $body['id_cliente'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_empleado', $body['id_empleado'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_metodo', $body['id_metodo'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':fecha_venta', $body['fecha_venta'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total', $body['total'], PDO::PARAM_STR);
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