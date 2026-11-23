<?php
try {
    $SQL_Query = 'UPDATE address SET
        street = :street,
        ext_number = :ext_number,
        int_number = :int_number,
        neighborhood = :neighborhood,
        city = :city,
        id_state = :id_state,
        zip_code = :zip_code,
        reference = :reference,
        source_node = :source_node
        WHERE
        deleted = 0 AND
        id_address = :id_address';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_address', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':street', $body['street'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':ext_number', $body['ext_number'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':int_number', $body['int_number'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':neighborhood', $body['neighborhood'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':city', $body['city'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':id_state', $body['id_state'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':zip_code', $body['zip_code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':reference', $body['reference'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $newData = $body;
    } else {
        $DB_Error = api_error(404, 'El registro no existe o fue eliminado.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
