<?php
/*
 * POST /orders/checkout  (requiere sesión de cliente)
 * {
 *   "id_store": 4, "delivery_type": "shipping" | "pickup",
 *   "payment_method": "card" | "oxxo" | "transfer" | "cash",
 *   "promotion_code": "BIENVENIDA10", "notes": "...",
 *   "address": {"street": "...", "ext_number": "...", "int_number": "", "neighborhood": "...",
 *               "city": "...", "id_state": 1, "zip_code": "22000", "reference": "..."},
 *   "items": [{"id_variant": 1, "quantity": 2}],
 *   "gift": {"occasion": "birthday", "color": "red", "recipient": "Ana", "message": "..."}   (opcional, $30)
 * }
 * El cliente sale de la sesión, nunca del cuerpo de la petición. Precios,
 * descuento (cupón + tipo de cuenta), envío, total, stock y pago se resuelven
 * en dbo.sp_crear_pedido.
 */
$CheckoutCustomer = null;
try {
    $CheckoutCustomer = store_current_customer($DB_Connector);
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}

if ($DB_Error === null && $CheckoutCustomer === null) {
    $DB_Error = api_error(401, 'Inicia sesión para completar la compra.');
}

if ($DB_Error === null) {
    try {
        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
        $address = isset($body['address']) && is_array($body['address']) ? $body['address'] : [];
        $deliveryType = $body['delivery_type'] ?? 'pickup';
        $idStore = $deliveryType === 'shipping' ? STORE_ONLINE_ID : ($body['id_store'] ?? null);

        $SQL_Query = 'EXEC dbo.sp_crear_pedido
            @id_store = :id_store,
            @id_customer = :id_customer,
            @promotion_code = :promotion_code,
            @channel = :channel,
            @items = :items,
            @notes = :notes,
            @source_node = :source_node,
            @delivery_type = :delivery_type,
            @payment_method = :payment_method,
            @street = :street,
            @ext_number = :ext_number,
            @int_number = :int_number,
            @neighborhood = :neighborhood,
            @city = :city,
            @id_state = :id_state,
            @zip_code = :zip_code,
            @reference = :reference,
            @gift_wrap = :gift_wrap,
            @gift_occasion = :gift_occasion,
            @gift_color = :gift_color,
            @gift_recipient = :gift_recipient,
            @gift_message = :gift_message';

        $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
        $SQL_Sentence->bindValue(':id_store', $idStore, PDO::PARAM_INT);
        $SQL_Sentence->bindValue(':id_customer', $CheckoutCustomer, PDO::PARAM_INT);
        $SQL_Sentence->bindValue(':promotion_code', $body['promotion_code'] ?? null, PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':channel', 'online', PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':items', json_encode($items), PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':notes', $body['notes'] ?? null, PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':source_node', 'web_node', PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':delivery_type', $deliveryType, PDO::PARAM_STR);
        $SQL_Sentence->bindValue(':payment_method', $body['payment_method'] ?? null, PDO::PARAM_STR);
        foreach (['street', 'ext_number', 'int_number', 'neighborhood', 'city', 'zip_code', 'reference'] as $field) {
            $SQL_Sentence->bindValue(':' . $field, isset($address[$field]) ? (string)$address[$field] : null, PDO::PARAM_STR);
        }
        $SQL_Sentence->bindValue(':id_state', $address['id_state'] ?? null, PDO::PARAM_INT);

        $gift = isset($body['gift']) && is_array($body['gift']) ? $body['gift'] : null;
        $SQL_Sentence->bindValue(':gift_wrap', $gift !== null ? 1 : 0, PDO::PARAM_INT);
        foreach (['occasion' => ':gift_occasion', 'color' => ':gift_color', 'recipient' => ':gift_recipient', 'message' => ':gift_message'] as $key => $param) {
            $value = $gift !== null && isset($gift[$key]) ? trim((string)$gift[$key]) : null;
            $SQL_Sentence->bindValue($param, $value === '' ? null : $value, $value === null || $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $SQL_Sentence->execute();

        $Checkout = $SQL_Sentence->fetch(PDO::FETCH_ASSOC);
        if (!$Checkout) {
            $DB_Error = api_error(500, 'El pedido no devolvió resultado.');
        } else {
            $Checkout['id_order'] = (int)$Checkout['id_order'];
            $Checkout['priority_shipping'] = (bool)$Checkout['priority_shipping'];
            $Checkout['gift_cost'] = (float)$Checkout['gift_cost'];

            $CheckoutOrder = $Checkout['id_order'];
            notify_safely(function () use ($DB_Connector, $CheckoutOrder) {
                notify_order_created($DB_Connector, $CheckoutOrder);
            });

            /* El pedido queda en el nodo central. Al terminar la petición,
               dist_after_write() lo copia a los fragmentos y al espejo; si la
               sucursal está caída, se pone al día cuando vuelva a responder. */
        }
    }
    catch (PDOException $ex) {
        $DB_Error = api_db_error($ex);
    }
}
