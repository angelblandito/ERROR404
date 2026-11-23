<?php
try {
    $SQL_Query = 'UPDATE owner SET
        first_name = :first_name,
        last_name_paternal = :last_name_paternal,
        last_name_maternal = :last_name_maternal,
        email = :email,
        phone = :phone,
        password_hash = :password_hash,
        active = :active,
        source_node = :source_node
        WHERE
        deleted = 0 AND
        id_owner = :id_owner';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_owner', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':first_name', $body['first_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_paternal', $body['last_name_paternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_maternal', $body['last_name_maternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':password_hash', $body['password_hash'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':active', $body['active'], PDO::PARAM_INT);
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
