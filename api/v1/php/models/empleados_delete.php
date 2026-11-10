<?php
try {
    $SQL_Query = 'DELETE FROM empleados 
        WHERE 
        id_empleado = :id_empleado';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_empleado', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $newData = "BORRADO";
    } else {
        $newData = 'Error: Cannot delete record';
    };
}
catch (PDOException $ex) {
    $newData = "Hubo un error!!!!!!!!!!!!";
};
?>