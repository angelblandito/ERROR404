<?php
try {
    $SQL_Query = 'INSERT INTO empleados VALUES (
        :id_sucursal, 
        :nombre_completo, 
        :puesto, 
        :fecha_contratacion)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_sucursal', $body['id_sucursal'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':nombre_completo', $body['nombre_completo'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':puesto', $body['puesto'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':fecha_contratacion', $body['fecha_contratacion'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $EmpleadoId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $EmpleadoId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $EmpleadoId = 'Error: ' . $ex->getMessage();
};
?>