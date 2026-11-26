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
 *   POST /support/tickets       Open a ticket: {subject, message, id_order?, urgent?}
 *   GET  /me/priority           Progress of an Estándar account towards Prioritario
 *   POST /me/priority/upgrade   Move the account to Prioritario when it meets the rules
 *
 * The priority of a ticket and its response time come from the account type:
 * Prioritario = 'high' and 15 minutes; Estándar = 'normal' and 24 hours. A ticket
 * marked urgent (emergencies: unrecognized charge, lost order, an injury with a
 * product) is always 'high' and is answered in at most URGENT_MINUTES.
 *
 * Rules to become Prioritario (the same ones dbo.sp_evaluar_clientes_prioritarios
 * applies when an administrator evaluates every customer):
 *   - more than PRIORITY_MIN_SPENT pesos in paid, shipped or delivered orders, and
 *   - more than PRIORITY_MIN_DAYS days with the account.
 *
 * Layer: services. Business rules and SQL, no HTTP. The routes live in Routers and
 * the controllers call these methods with the session payload.
 */
final class AccountService
{
    /** Pesos bought (paid, shipped or delivered orders) needed to become Prioritario. */
    public const PRIORITY_MIN_SPENT = 50000.00;

    /** Days with the account needed to become Prioritario (more than one year). */
    public const PRIORITY_MIN_DAYS = 365;

    /** Maximum response time of an urgent ticket, whatever the account type. */
    public const URGENT_MINUTES = 60;

    /**
     * Progress towards Prioritario. Pure function (no database) so the rule can be
     * tested on its own.
     *
     * @return array<string, mixed>
     */
    public static function priorityProgress(string $tier, float $spent, int $days, string $since): array
    {
        $spent = round(max(0.0, $spent), 2);
        $days = max(0, $days);
        $meetsSpent = $spent > self::PRIORITY_MIN_SPENT;
        $meetsDays = $days > self::PRIORITY_MIN_DAYS;
        $sinceDate = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($since, 0, 10));
        return [
            'tier' => $tier,
            'customer_since' => substr($since, 0, 10),
            'spent' => $spent,
            'required_spent' => self::PRIORITY_MIN_SPENT,
            'missing_spent' => $meetsSpent ? 0.0 : round(self::PRIORITY_MIN_SPENT - $spent + 0.01, 2),
            'spent_percent' => (int) min(100, floor($spent * 100 / self::PRIORITY_MIN_SPENT)),
            'meets_spent' => $meetsSpent,
            'days' => $days,
            'required_days' => self::PRIORITY_MIN_DAYS,
            'missing_days' => $meetsDays ? 0 : self::PRIORITY_MIN_DAYS + 1 - $days,
            'days_percent' => (int) min(100, floor($days * 100 / (self::PRIORITY_MIN_DAYS + 1))),
            'meets_days' => $meetsDays,
            'days_ready_on' => $sinceDate ? $sinceDate->modify('+' . (self::PRIORITY_MIN_DAYS + 1) . ' days')->format('Y-m-d') : null,
            'eligible' => $tier === 'standard' && $meetsSpent && $meetsDays,
        ];
    }

    /** @return array<string, mixed> */
    public static function priorityStatus(?array $actor): array
    {
        $idCustomer = StoreService::requireCustomer($actor);
        return self::loadPriorityStatus(StoreService::db(), $idCustomer);
    }

    /** @return array<string, mixed> */
    private static function loadPriorityStatus(PDO $pdo, int $idCustomer): array
    {
        $rows = StoreService::rows($pdo, "
            SELECT t.code AS tier, CONVERT(CHAR(10), c.customer_since, 23) AS customer_since,
                   DATEDIFF(DAY, c.customer_since, CAST(SYSUTCDATETIME() AS DATE)) AS days,
                   (SELECT ISNULL(SUM(co.total), 0)
                    FROM dbo.customer_order AS co
                    WHERE co.id_customer = c.id_customer AND co.deleted = 0
                      AND co.status IN ('paid', 'shipped', 'delivered')) AS spent
            FROM dbo.customer AS c
            JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
            WHERE c.id_customer = :id AND c.deleted = 0", [':id' => $idCustomer]);
        if (!$rows) {
            throw new AppError('La cuenta no existe.', 404);
        }
        $r = $rows[0];
        return self::priorityProgress((string) $r['tier'], (float) $r['spent'], (int) $r['days'], (string) $r['customer_since']);
    }

    /**
     * The customer asks to move up. Only an Estándar account that meets both
     * rules changes, and only to Prioritario (never to Administrador).
     *
     * @return array<string, mixed>
     */
    public static function upgradeToPriority(?array $actor): array
    {
        $idCustomer = StoreService::requireCustomer($actor);
        $pdo = StoreService::db();
        $status = self::loadPriorityStatus($pdo, $idCustomer);

        if ($status['tier'] !== 'standard') {
            throw new AppError('Tu cuenta ya tiene los beneficios de Prioritario.', 409);
        }
        if (!$status['eligible']) {
            $missing = [];
            if (!$status['meets_spent']) {
                $missing[] = 'comprar ' . NotificationService::money((float) $status['missing_spent']) . ' más';
            }
            if (!$status['meets_days']) {
                $missing[] = 'esperar ' . $status['missing_days'] . ' día(s) más con tu cuenta';
            }
            throw new AppError('Todavía no cumples los requisitos: te falta ' . implode(' y ', $missing) . '.', 403);
        }

        $stmt = $pdo->prepare("
            UPDATE dbo.customer
            SET id_account_tier = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'priority'),
                updated_at = SYSUTCDATETIME()
            WHERE id_customer = :id AND deleted = 0
              AND id_account_tier = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'standard')");
        $stmt->execute([':id' => $idCustomer]);

        $profile = StoreService::profile($pdo, $idCustomer);
        NotificationService::safely(static function () use ($pdo, $idCustomer, $profile): void {
            NotificationService::tierUpgrade($pdo, $idCustomer, (string) $profile['first_name']);
        });

        return ['customer' => $profile, 'priority' => self::loadPriorityStatus($pdo, $idCustomer)];
    }

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
        $urgent = !empty($body['urgent']);
        $minutes = (int) $account['support_minutes'];
        if ($urgent) {
            $minutes = min($minutes, self::URGENT_MINUTES);
            $subject = mb_substr('URGENTE: ' . $subject, 0, 100);
        }

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
        $stmt->bindValue(':priority', $urgent || StoreService::isPriority($account) ? 'high' : 'normal', PDO::PARAM_STR);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        $rows = StoreService::rows($pdo, '
            SELECT id_ticket, id_order, subject, message, priority, status, response,
                   created_at, respond_by, answered_at
            FROM dbo.support_ticket
            WHERE id_ticket = :id', [':id' => (int)$pdo->lastInsertId()]);
        $ticket = self::ticketRow($rows[0]);
        if ($urgent) {
            $idCustomer = (int) $account['id_customer'];
            $profile = StoreService::profile($pdo, $idCustomer);
            NotificationService::safely(static function () use ($pdo, $idCustomer, $ticket, $profile, $minutes): void {
                NotificationService::urgentTicket($pdo, $idCustomer, $ticket['id_ticket'], (string) $profile['first_name'], $minutes);
            });
        }
        return $ticket;
    }
}
