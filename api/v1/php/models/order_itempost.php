<?php
try {
    $SQL_Query = 'INSERT INTO order_item (id_order, id_variant, quantity, unit_price, source_node)
        SELECT :id_order, pv.id_variant, :quantity, p.base_price, :source_node
        FROM product_variant AS pv
        JOIN product AS p ON p.id_product = pv.id_product
        WHERE pv.id_variant = :id_variant AND pv.deleted = 0 AND p.deleted = 0';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_order', $body['id_order'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_variant', $body['id_variant'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':quantity', $body['quantity'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $OrderItemId = $DB_Connector->lastInsertId(); // Id del registro recién creado
    } else {
        $DB_Error = api_error(400, 'El producto no existe o ya no está a la venta.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
