<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use PDO;

/**
 * Account benefits: announcements and customer support.
 *
 *   GET  /store/announcements   Current announcements (audience 'priority' only for
 *                               Prioritario and Administrador accounts)
 *   GET  /support/tickets       Support tickets of the signed-in customer
 *   POST /support/tickets       Open a ticket: {subject, message, id_order?}
 *
 * The priority of a ticket and its response time come from the account type:
 * Prioritario = 'high' and 15 minutes; Estándar = 'normal' and 24 hours.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class AccountService
{
    public static function announcements(?array $actor): array
    {
        $pdo = StoreService::db();
        $priority = StoreService::isPriority($actor);

        $rows = StoreService::rows($pdo, "
            SELECT id_announcement, audience, title, body, starts_on, ends_on
            FROM dbo.announcement
            WHERE deleted = 0
              AND starts_on <= CAST(SYSUTCDATETIME() AS DATE)
              AND (ends_on IS NULL OR ends_on >= CAST(SYSUTCDATETIME() AS DATE))
              AND (audience = 'all' OR :priority = 1)
            ORDER BY CASE audience WHEN 'priority' THEN 0 ELSE 1 END, starts_on DESC, id_announcement DESC",
            [':priority' => $priority ? 1 : 0]);

        foreach ($rows as &$r) {
            $r['id_announcement'] = (int)$r['id_announcement'];
        }
        unset($r);
        return $rows;
    }

    public static function ticketRow(array $t): array
    {
        $t['id_ticket'] = (int)$t['id_ticket'];
        $t['id_order'] = $t['id_order'] !== null ? (int)$t['id_order'] : null;
        return $t;
    }

    public static function myTickets(?array $actor): array
    {
        $pdo = StoreService::db();
        $idCustomer = StoreService::requireCustomer($actor);

        $rows = StoreService::rows($pdo, '
            SELECT id_ticket, id_order, subject, message, priority, status, response,
                   created_at, respond_by, answered_at
            FROM dbo.support_ticket
            WHERE id_customer = :id AND deleted = 0
            ORDER BY created_at DESC, id_ticket DESC', [':id' => $idCustomer]);

        return array_map([self::class, 'ticketRow'], $rows);
    }

    public static function createTicket(?array $actor, array $body): array
    {
        $pdo = StoreService::db();
        $account = $actor;
        if ($account === null) {
            throw new AppError('Inicia sesión para contactar a soporte.', 401);
        }

        $subject = StoreService::text($body, 'subject', 3, 100, 'El asunto');
        $message = StoreService::text($body, 'message', 10, 1000, 'El mensaje');
        $idOrder = isset($body['id_order']) && $body['id_order'] !== '' ? (int) StoreService::number($body, 'id_order') : null;

        if ($idOrder !== null) {
            $own = StoreService::rows($pdo, '
                SELECT 1 AS ok FROM dbo.customer_order
                WHERE id_order = :id_order AND id_customer = :id_customer AND deleted = 0',
                [':id_order' => $idOrder, ':id_customer' => $account['id_customer']]);
            if (!$own) {
                throw new AppError('El pedido no pertenece a tu cuenta.', 400);
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO dbo.support_ticket (id_customer, id_order, subject, message, priority, respond_by, source_node)
            VALUES (:id_customer, :id_order, :subject, :message, :priority,
                    DATEADD(MINUTE, :minutes, SYSUTCDATETIME()), 'web_node')");
        $stmt->bindValue(':id_customer', $account['id_customer'], PDO::PARAM_INT);
        $stmt->bindValue(':id_order', $idOrder, $idOrder === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':priority', StoreService::isPriority($account) ? 'high' : 'normal', PDO::PARAM_STR);
        $stmt->bindValue(':minutes', $account['support_minutes'], PDO::PARAM_INT);
        $stmt->execute();

        $rows = StoreService::rows($pdo, '
            SELECT id_ticket, id_order, subject, message, priority, status, response,
                   created_at, respond_by, answered_at
            FROM dbo.support_ticket
            WHERE id_ticket = :id', [':id' => (int)$pdo->lastInsertId()]);
        return self::ticketRow($rows[0]);
    }
}
