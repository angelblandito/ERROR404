<?php
/*
 * POST /orders/checkout
 * {
 *   "id_store": 1, "id_customer": 1, "id_employee": null,
 *   "channel": "in_store", "promotion_code": "BIENVENIDA10", "notes": "...",
 *   "items": [{"id_variant": 1, "quantity": 2}]
 * }
 * Precios, descuento, total y stock se calculan en dbo.sp_crear_pedido.
 */
try {
    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];

    $SQL_Query = 'EXEC dbo.sp_crear_pedido
        @id_store = :id_store,
        @id_customer = :id_customer,
        @id_employee = :id_employee,
        @promotion_code = :promotion_code,
        @channel = :channel,
        @items = :items,
        @notes = :notes,
        @source_node = :source_node';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindValue(':id_store', $body['id_store'] ?? null, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':id_customer', $body['id_customer'] ?? null, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':id_employee', $body['id_employee'] ?? null, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':promotion_code', $body['promotion_code'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':channel', $body['channel'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':items', json_encode($items), PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':notes', $body['notes'] ?? null, PDO::PARAM_STR);
    $SQL_Sentence->bindValue(':source_node', $body['source_node'] ?? 'web_node', PDO::PARAM_STR);
    $SQL_Sentence->execute();

    $Checkout = $SQL_Sentence->fetch(PDO::FETCH_ASSOC);
    if (!$Checkout) {
        $DB_Error = api_error(500, 'El pedido no devolvió resultado.');
    } else {
        $Checkout['id_order'] = (int)$Checkout['id_order'];
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
