<?php
try {
    $SQL_Query = 'UPDATE customer_order SET
        id_store = :id_store, 
        id_customer = :id_customer, 
        id_employee = :id_employee, 
        id_promotion = :id_promotion, 
        status = :status, 
        channel = :channel, 
        subtotal = :subtotal, 
        discount = :discount, 
        total = :total, 
        notes = :notes, 
        source_node = :source_node, 
        order_date = :order_date 
        WHERE 
        deleted = 0 AND 
        id_order = :id_order AND 
        (:rv_check IS NULL OR row_version = CAST(CAST(:rv_value AS BIGINT) AS BINARY(8)))';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_order', $resourceId, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':rv_check', $body['row_version'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':rv_value', $body['row_version'] ?? null, PDO::PARAM_STR);
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
    $SQL_Sentence->bindParam(':order_date', $body['order_date'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $newData = $body;
    } else {
        $DB_Error = isset($body['row_version'])
            ? api_error(409, 'El registro fue modificado por otro usuario. Vuelva a cargarlo.')
            : api_error(404, 'El registro no existe o fue eliminado.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>