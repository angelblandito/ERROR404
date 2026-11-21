<?php
try {
    $SQL_Query = 'UPDATE employee SET
        id_store = :id_store,
        id_role = :id_role,
        first_name = :first_name,
        last_name_paternal = :last_name_paternal,
        last_name_maternal = :last_name_maternal,
        phone = :phone,
        phone_verified = :phone_verified,
        email = :email,
        password_hash = :password_hash,
        must_change_password = :must_change_password,
        hire_date = :hire_date,
        source_node = :source_node
        WHERE
        deleted = 0 AND
        id_employee = :id_employee';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_employee', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_role', $body['id_role'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':first_name', $body['first_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_paternal', $body['last_name_paternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':last_name_maternal', $body['last_name_maternal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':phone', $body['phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':phone_verified', $body['phone_verified'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':password_hash', $body['password_hash'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':must_change_password', $body['must_change_password'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':hire_date', $body['hire_date'], PDO::PARAM_STR);
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
