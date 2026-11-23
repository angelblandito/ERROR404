<?php
try {
    $SQL_Query = 'INSERT INTO inventory (id_store, id_variant, stock, min_stock, source_node) VALUES (
        :id_store,
        :id_variant,
        :stock,
        :min_stock,
        :source_node)';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_variant', $body['id_variant'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':stock', $body['stock'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':min_stock', $body['min_stock'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $InventoryId = $DB_Connector->lastInsertId(); // Id del registro recién creado
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
