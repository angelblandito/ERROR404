<?php
try {
    $SQL_Query = 'INSERT INTO store (id_owner, id_store_type, id_address, store_name, tax_id, contact_phone, email, whatsapp, opened_at, active, source_node) VALUES (
        :id_owner,
        :id_store_type,
        :id_address,
        :store_name,
        :tax_id,
        :contact_phone,
        :email,
        :whatsapp,
        :opened_at,
        :active,
        :source_node)';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_owner', $body['id_owner'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_store_type', $body['id_store_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_address', $body['id_address'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':store_name', $body['store_name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':tax_id', $body['tax_id'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':contact_phone', $body['contact_phone'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':email', $body['email'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':whatsapp', $body['whatsapp'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':opened_at', $body['opened_at'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':active', $body['active'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $StoreId = $DB_Connector->lastInsertId(); // Id del registro recién creado
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
