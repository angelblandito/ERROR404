<?php
try {
    $SQL_Query = 'UPDATE metodos_pago SET
        metodo = :metodo 
        WHERE 
        id_metodo = :id_metodo';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_metodo', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':metodo', $body['metodo'], PDO::PARAM_STR);
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