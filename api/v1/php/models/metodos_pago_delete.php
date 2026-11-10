<?php
try {
    $SQL_Query = 'DELETE FROM metodos_pago 
        WHERE 
        id_metodo = :id_metodo';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_metodo', $resourceId, PDO::PARAM_INT);
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