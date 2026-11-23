<?php
try {
    [$Offset, $PageSize] = api_page();

    $SQL_Query = 'SELECT id_payment, id_order, amount, currency, payment_date, method, external_reference, status, source_node, updated_at, deleted
        FROM payment
        WHERE deleted = 0
        ORDER BY id_payment
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindValue(':offset', $Offset, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':page_size', $PageSize, PDO::PARAM_INT);
    $SQL_Sentence->execute();

    $Elements = $SQL_Sentence->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
