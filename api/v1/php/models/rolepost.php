<?php
try {
    $SQL_Query = 'INSERT INTO role (id_role, code, name) VALUES (
        :id_role,
        :code,
        :name)';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_role', $body['id_role'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $RoleId = $body['id_role']; // La llave primaria la envía el cliente: la tabla no usa IDENTITY
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
