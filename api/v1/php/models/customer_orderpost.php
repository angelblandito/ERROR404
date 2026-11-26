<?php
try {
    $SQL_Query = 'INSERT INTO customer_order (id_store, id_customer, id_employee, id_promotion, status, channel, subtotal, discount, total, notes, source_node) VALUES (
        :id_store,
        :id_customer,
        :id_employee,
        :id_promotion,
        :status,
        :channel,
        :subtotal,
        :discount,
        :total,
        :notes,
        :source_node);
        SELECT CAST(SCOPE_IDENTITY() AS INT) AS id_order;';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_store', $body['id_store'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_customer', $body['id_customer'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_promotion', $body['id_promotion'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':status', $body['status'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':channel', $body['channel'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':subtotal', $body['subtotal'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':discount', $body['discount'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':total', $body['total'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':notes', $body['notes'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    $Inserted = $SQL_Sentence->rowCount();

    // SCOPE_IDENTITY y no lastInsertId(): el trigger de historial inserta en
    // order_status_history y lastInsertId() devolvería ese id.
    $SQL_Sentence->nextRowset();
    $NewOrder = $SQL_Sentence->fetch(PDO::FETCH_ASSOC);

    if ($Inserted != 0 && $NewOrder) {
        $CustomerOrderId = (int)$NewOrder['id_order'];
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
