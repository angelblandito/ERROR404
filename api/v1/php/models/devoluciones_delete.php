<?php
try {
    $SQL_Query = 'DELETE FROM Devoluciones 
        WHERE 
        devolucion_id = :devolucion_id';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':devolucion_id', $resourceId, PDO::PARAM_INT);
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