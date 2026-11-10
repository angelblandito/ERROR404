<?php
try {
    $SQL_Query = 'DELETE FROM categorias 
        WHERE 
        id_categoria = :id_categoria';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_categoria', $resourceId, PDO::PARAM_INT);
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