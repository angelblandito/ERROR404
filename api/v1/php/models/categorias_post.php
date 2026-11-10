<?php
try {
    $SQL_Query = 'INSERT INTO categorias VALUES (
        :nombre, 
        :descripcion)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':nombre', $body['nombre'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':descripcion', $body['descripcion'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $CategoriaId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $CategoriaId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $CategoriaId = 'Error: ' . $ex->getMessage();
};
?>