<?php
try {
    $SQL_Query = 'INSERT INTO payment (id_order, amount, currency, method, external_reference, status, source_node) VALUES (
        :id_order, 
        :amount, 
        :currency, 
        :method, 
        :external_reference, 
        :status, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_order', $body['id_order'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':amount', $body['amount'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':currency', $body['currency'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':method', $body['method'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':external_reference', $body['external_reference'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':status', $body['status'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $PaymentId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $PaymentId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $PaymentId = 'Error: ' . $ex->getMessage();
};
?>