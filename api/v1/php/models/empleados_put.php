<?php
try {
    $SQL_Query = 'UPDATE empleados SET
        id_sucursal = :id_sucursal, 
        nombre_completo = :nombre_completo, 
        puesto = :puesto, 
        fecha_contratacion = :fecha_contratacion 
        WHERE 
        id_empleado = :id_empleado';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_empleado', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre_completo', $body['nombre_completo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':puesto', $body['puesto'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_contratacion', $body['fecha_contratacion'], PDO::PARAM_STR);
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