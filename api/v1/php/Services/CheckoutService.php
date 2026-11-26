<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use PDO;

/**
 * Online purchase.
 *
 *   POST /orders/checkout  (requires a customer session)
 *   {
 *     "id_store": 1, "delivery_type": "shipping" | "pickup",
 *     "payment_method": "card" | "oxxo" | "transfer" | "cash",
 *     "promotion_code": "BIENVENIDA10", "notes": "...",
 *     "address": {"street": "...", "ext_number": "...", "int_number": "", "neighborhood": "...",
 *                 "city": "...", "id_state": 1, "zip_code": "22000", "reference": "..."},
 *     "items": [{"id_variant": 1, "quantity": 2}],
 *     "gift": {"occasion": "birthday", "color": "red", "recipient": "Ana", "message": "..."}   (optional, $30)
 *   }
 *
 * The customer comes from the session, never from the request body. Prices, discount (coupon +
 * account type), shipping, total, stock and payment are resolved by dbo.sp_crear_pedido in one
 * transaction. The order is saved on the central node; after the request, SyncMiddleware copies
 * it to the fragments and to the Azure mirror.
 * Layer: services.
 */
final class CheckoutService
{
    public static function create(?array $actor, array $body): array
    {
        if ($actor === null) {
            throw new AppError('Inicia sesión para completar la compra.', 401);
        }
        $pdo = StoreService::db();

        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
        $address = isset($body['address']) && is_array($body['address']) ? $body['address'] : [];
        $deliveryType = StoreService::field($body, 'delivery_type', 'pickup');
        $idStore = $deliveryType === 'shipping' ? StoreService::ONLINE_ID : ($body['id_store'] ?? null);
        /* The types are checked here; prices, stock and rules are checked by dbo.sp_crear_pedido */
        if ($idStore !== null && !is_int($idStore)) {
            throw new AppError('La sucursal no es válida.', 400);
        }
        if (isset($address['id_state']) && !is_int($address['id_state'])) {
            throw new AppError('El estado del domicilio no es válido.', 400);
        }
        if (count($items) > 100) {
            throw new AppError('El pedido tiene demasiados artículos.', 400);
        }
        /* Only what and how many travel: the database sets the price, the discount and the stock */
        $items = array_map(static function ($item): array {
            if (!is_array($item)) {
                throw new AppError('Cada artículo necesita id_variant y quantity.', 400);
            }
            return [
                'id_variant' => (int) StoreService::number($item, 'id_variant', 0),
                'quantity' => (int) StoreService::number($item, 'quantity', 0),
            ];
        }, array_values($items));
        $promotionCode = StoreService::field($body, 'promotion_code');
        $notes = StoreService::field($body, 'notes');
        $paymentMethod = StoreService::field($body, 'payment_method');

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
        $stmt->bindValue(':promotion_code', $promotionCode === '' ? null : $promotionCode, $promotionCode === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':channel', 'online', PDO::PARAM_STR);
        $stmt->bindValue(':items', json_encode($items), PDO::PARAM_STR);
        $stmt->bindValue(':notes', $notes === '' ? null : mb_substr($notes, 0, 255), $notes === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':source_node', 'web_node', PDO::PARAM_STR);
        $stmt->bindValue(':delivery_type', $deliveryType, PDO::PARAM_STR);
        $stmt->bindValue(':payment_method', $paymentMethod === '' ? null : $paymentMethod, $paymentMethod === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        foreach (['street', 'ext_number', 'int_number', 'neighborhood', 'city', 'zip_code', 'reference'] as $field) {
            $stmt->bindValue(':' . $field, isset($address[$field]) ? StoreService::field($address, $field) : null, PDO::PARAM_STR);
        }
        $stmt->bindValue(':id_state', $address['id_state'] ?? null, PDO::PARAM_INT);

        $gift = isset($body['gift']) && is_array($body['gift']) ? $body['gift'] : null;
        $stmt->bindValue(':gift_wrap', $gift !== null ? 1 : 0, PDO::PARAM_INT);
        foreach (['occasion' => ':gift_occasion', 'color' => ':gift_color', 'recipient' => ':gift_recipient', 'message' => ':gift_message'] as $key => $param) {
            $value = $gift !== null && isset($gift[$key]) ? trim(StoreService::field($gift, $key)) : null;
            $stmt->bindValue($param, $value === '' ? null : $value, $value === null || $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $stmt->execute();

        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new AppError('El pedido no devolvió resultado.', 500);
        }
        $order['id_order'] = (int)$order['id_order'];
        $order['priority_shipping'] = (bool)$order['priority_shipping'];
        $order['gift_cost'] = (float)$order['gift_cost'];

        $idOrder = $order['id_order'];
        NotificationService::safely(function () use ($pdo, $idOrder) {
            NotificationService::orderCreated($pdo, $idOrder);
        });
        return $order;
    }
}
