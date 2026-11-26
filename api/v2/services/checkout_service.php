<?php
/*
 * Compra en línea.
 *
 *   POST /orders/checkout  (requiere sesión de cliente)
 *   {
 *     "id_store": 1, "delivery_type": "shipping" | "pickup",
 *     "payment_method": "card" | "oxxo" | "transfer" | "cash",
 *     "promotion_code": "BIENVENIDA10", "notes": "...",
 *     "address": {"street": "...", "ext_number": "...", "int_number": "", "neighborhood": "...",
 *                 "city": "...", "id_state": 1, "zip_code": "22000", "reference": "..."},
 *     "items": [{"id_variant": 1, "quantity": 2}],
 *     "gift": {"occasion": "birthday", "color": "red", "recipient": "Ana", "message": "..."}   (opcional, $30)
 *   }
 *
 * El cliente sale de la sesión, nunca del cuerpo de la petición. Precios, descuento (cupón + tipo
 * de cuenta), envío, total, stock y pago se resuelven en dbo.sp_crear_pedido, en una sola
 * transacción. El pedido queda en el nodo central; al terminar la petición la tarea posterior
 * (dist_after_write) lo copia a los fragmentos y al espejo en Azure.
 * Layer: services.
 */

function checkout_create(?array $actor, array $body): array
{
    if ($actor === null) {
        throw new HttpError(401, 'Inicia sesión para completar la compra.');
    }
    $pdo = store_db();

    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
    $address = isset($body['address']) && is_array($body['address']) ? $body['address'] : [];
    $deliveryType = $body['delivery_type'] ?? 'pickup';
    $idStore = $deliveryType === 'shipping' ? STORE_ONLINE_ID : ($body['id_store'] ?? null);

    $stmt = $pdo->prepare('EXEC dbo.sp_crear_pedido
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
        @gift_message = :gift_message');

    $stmt->bindValue(':id_store', $idStore, PDO::PARAM_INT);
    $stmt->bindValue(':id_customer', (int)$actor['id_customer'], PDO::PARAM_INT);
    $stmt->bindValue(':promotion_code', $body['promotion_code'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':channel', 'online', PDO::PARAM_STR);
    $stmt->bindValue(':items', json_encode($items), PDO::PARAM_STR);
    $stmt->bindValue(':notes', $body['notes'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':source_node', 'web_node', PDO::PARAM_STR);
    $stmt->bindValue(':delivery_type', $deliveryType, PDO::PARAM_STR);
    $stmt->bindValue(':payment_method', $body['payment_method'] ?? null, PDO::PARAM_STR);
    foreach (['street', 'ext_number', 'int_number', 'neighborhood', 'city', 'zip_code', 'reference'] as $field) {
        $stmt->bindValue(':' . $field, isset($address[$field]) ? (string)$address[$field] : null, PDO::PARAM_STR);
    }
    $stmt->bindValue(':id_state', $address['id_state'] ?? null, PDO::PARAM_INT);

    $gift = isset($body['gift']) && is_array($body['gift']) ? $body['gift'] : null;
    $stmt->bindValue(':gift_wrap', $gift !== null ? 1 : 0, PDO::PARAM_INT);
    foreach (['occasion' => ':gift_occasion', 'color' => ':gift_color', 'recipient' => ':gift_recipient', 'message' => ':gift_message'] as $key => $param) {
        $value = $gift !== null && isset($gift[$key]) ? trim((string)$gift[$key]) : null;
        $stmt->bindValue($param, $value === '' ? null : $value, $value === null || $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new HttpError(500, 'El pedido no devolvió resultado.');
    }
    $order['id_order'] = (int)$order['id_order'];
    $order['priority_shipping'] = (bool)$order['priority_shipping'];
    $order['gift_cost'] = (float)$order['gift_cost'];

    $idOrder = $order['id_order'];
    notify_safely(function () use ($pdo, $idOrder) {
        notify_order_created($pdo, $idOrder);
    });
    return $order;
}

