<?php
try {
    $SQL_Query = 'UPDATE payment SET
        id_order = :id_order,
        amount = :amount,
        currency = :currency,
        method = :method,
        external_reference = :external_reference,
        status = :status,
        source_node = :source_node,
        payment_date = :payment_date
        WHERE
        deleted = 0 AND
        id_payment = :id_payment';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_payment', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_order', $body['id_order'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':amount', $body['amount'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':currency', $body['currency'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':method', $body['method'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':external_reference', $body['external_reference'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':status', $body['status'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':payment_date', $body['payment_date'], PDO::PARAM_STR);
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
