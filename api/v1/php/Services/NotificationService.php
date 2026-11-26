<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Settings;
use PDO;
use Throwable;

/**
 * Store emails.
 *
 * There is no SMTP server: every email is saved in dbo.email_outbox and the
 * administrator reads them in the panel (Correos tab). If saving fails, the
 * main operation (order, status change) is not cancelled.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class NotificationService
{
    public const STATUS_TEXT = [
        'pending' => 'Recibimos tu pedido y estamos esperando la confirmación del pago.',
        'paid' => 'Confirmamos tu pago. Ya estamos preparando tu pedido.',
        'shipped' => 'Tu pedido va en camino.',
        'delivered' => 'Tu pedido fue entregado. ¡Gracias por comprar en JAM Sport!',
        'cancelled' => 'Tu pedido fue cancelado. Si ya habías pagado, el reembolso se hace al mismo método de pago.',
        'returned' => 'Registramos la devolución de tu pedido. El reembolso se hace al mismo método de pago.',
    ];

    public const STATUS_NAME = [
        'pending' => 'Pendiente de pago',
        'paid' => 'Pagado',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
        'returned' => 'Devuelto',
    ];

    public static function storeLink(string $fragment): string
    {
        $base = rtrim((string)Settings::instance()->get('JAM_STORE_URL', ''), '/');
        return ($base !== '' ? $base . '/' : '') . $fragment;
    }

    public static function save(PDO $pdo, string $kind, int $idCustomer, ?int $idOrder, string $subject, string $body, ?string $link): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO dbo.email_outbox (id_customer, id_order, kind, to_email, subject, body, link, source_node)
            SELECT c.id_customer, :id_order, :kind, c.email, :subject, :body, :link, 'web_node'
            FROM dbo.customer AS c
            WHERE c.id_customer = :id_customer AND c.deleted = 0");
        $stmt->bindValue(':id_order', $idOrder, $idOrder === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind, PDO::PARAM_STR);
        $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindValue(':body', $body, PDO::PARAM_STR);
        $stmt->bindValue(':link', $link, $link === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id_customer', $idCustomer, PDO::PARAM_INT);
        $stmt->execute();
    }

    /* Send without interrupting the operation that triggered it. */
    public static function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            error_log('No se pudo registrar el correo: ' . $e->getMessage());
        }
    }

    public static function money(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }

    public static function welcome(PDO $pdo, int $idCustomer, string $firstName): void
    {
        self::save($pdo, 'welcome', $idCustomer, null,
            'Bienvenido a JAM Sport',
            "Hola $firstName, tu cuenta quedó lista. Usa el cupón BIENVENIDA10 en tu primera compra.",
            self::storeLink('#/tienda'));
    }

    public static function orderCreated(PDO $pdo, int $idOrder): void
    {
        $rows = StoreService::rows($pdo, "
            SELECT co.id_customer, co.total, co.delivery_type, co.priority_shipping, c.first_name, s.store_name,
                   g.recipient_name AS gift_recipient, CASE WHEN g.id_order IS NULL THEN 0 ELSE 1 END AS is_gift,
                   (SELECT SUM(quantity) FROM dbo.order_item WHERE id_order = co.id_order AND deleted = 0) AS units
            FROM dbo.customer_order AS co
            JOIN dbo.customer AS c ON c.id_customer = co.id_customer
            JOIN dbo.store AS s ON s.id_store = co.id_store
            LEFT JOIN dbo.order_gift AS g ON g.id_order = co.id_order
            WHERE co.id_order = :id", [':id' => $idOrder]);
        if (!$rows) {
            return;
        }
        $o = $rows[0];
        $delivery = $o['delivery_type'] === 'shipping'
            ? ((int)$o['priority_shipping'] === 1 ? 'Envío prioritario a domicilio.' : 'Envío a domicilio.')
            : 'Recoges en ' . $o['store_name'] . '.';

        self::save($pdo, 'order_created', (int)$o['id_customer'], $idOrder,
            "Confirmación de tu pedido #$idOrder",
            'Hola ' . $o['first_name'] . ', recibimos tu pedido #' . $idOrder . ' con ' . (int)$o['units']
                . ' pieza(s) por ' . self::money((float)$o['total']) . '. ' . $delivery
                . ((int)$o['is_gift'] === 1 ? ' Va con empaque especial de regalo' . ($o['gift_recipient'] ? ' para ' . $o['gift_recipient'] : '') . '.' : '')
                . ' Puedes seguir su estado en Mi cuenta.',
            self::storeLink('#/cuenta'));
    }

    public static function orderStatus(PDO $pdo, int $idOrder, string $status): void
    {
        $rows = StoreService::rows($pdo, '
            SELECT co.id_customer, c.first_name
            FROM dbo.customer_order AS co
            JOIN dbo.customer AS c ON c.id_customer = co.id_customer
            WHERE co.id_order = :id', [':id' => $idOrder]);
        if (!$rows) {
            return;
        }
        self::save($pdo, 'order_status', (int)$rows[0]['id_customer'], $idOrder,
            "Tu pedido #$idOrder: " . (self::STATUS_NAME[$status] ?? $status),
            'Hola ' . $rows[0]['first_name'] . '. ' . (self::STATUS_TEXT[$status] ?? ''),
            self::storeLink('#/cuenta'));
    }

    public static function passwordReset(PDO $pdo, int $idCustomer, string $firstName, string $link, int $minutes): void
    {
        self::save($pdo, 'password_reset', $idCustomer, null,
            'Restablece tu contraseña de JAM Sport',
            "Hola $firstName, recibimos una solicitud para cambiar tu contraseña. El enlace vence en $minutes minutos"
                . ' y sirve una sola vez. Si no fuiste tú, ignora este correo.',
            $link);
    }
}
