<?php
/*
 * Beneficios de las cuentas: comunicados y soporte técnico.
 *
 *   GET  /store/announcements   Comunicados vigentes (los de audience 'priority' solo
 *                               para cuentas Prioritario y Administrador)
 *   GET  /support/tickets       Tickets de soporte del cliente con sesión
 *   POST /support/tickets       Abrir un ticket: {subject, message, id_order?}
 *
 * La prioridad del ticket y su plazo de respuesta salen del tipo de cuenta:
 * Prioritario = 'high' y 15 minutos; Estándar = 'normal' y 24 horas.
 *
 * Layer: services. Logica de negocio y SQL; sin HTTP. Las rutas viven en routers y los
 * controllers de controllers llaman a estas funciones con el actor de la sesion.
 */

function account_announcements(?array $actor): array
{
    $pdo = store_db();
    $priority = store_is_priority($actor);

    $rows = store_rows($pdo, "
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

function account_ticket_row(array $t): array
{
    $t['id_ticket'] = (int)$t['id_ticket'];
    $t['id_order'] = $t['id_order'] !== null ? (int)$t['id_order'] : null;
    return $t;
}

function account_my_tickets(?array $actor): array
{
    $pdo = store_db();
    $idCustomer = store_require_customer($actor);

    $rows = store_rows($pdo, '
        SELECT id_ticket, id_order, subject, message, priority, status, response,
               created_at, respond_by, answered_at
        FROM dbo.support_ticket
        WHERE id_customer = :id AND deleted = 0
        ORDER BY created_at DESC, id_ticket DESC', [':id' => $idCustomer]);

    return array_map('account_ticket_row', $rows);
}

function account_create_ticket(?array $actor, array $body): array
{
    $pdo = store_db();
    $account = $actor;
    if ($account === null) {
        throw new HttpError(401, 'Inicia sesión para contactar a soporte.');
    }

    $subject = store_text($body, 'subject', 3, 100, 'El asunto');
    $message = store_text($body, 'message', 10, 1000, 'El mensaje');
    $idOrder = isset($body['id_order']) && $body['id_order'] !== '' ? (int)$body['id_order'] : null;

    if ($idOrder !== null) {
        $own = store_rows($pdo, '
            SELECT 1 AS ok FROM dbo.customer_order
            WHERE id_order = :id_order AND id_customer = :id_customer AND deleted = 0',
            [':id_order' => $idOrder, ':id_customer' => $account['id_customer']]);
        if (!$own) {
            throw new HttpError(400, 'El pedido no pertenece a tu cuenta.');
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
    $stmt->bindValue(':priority', store_is_priority($account) ? 'high' : 'normal', PDO::PARAM_STR);
    $stmt->bindValue(':minutes', $account['support_minutes'], PDO::PARAM_INT);
    $stmt->execute();

    $rows = store_rows($pdo, '
        SELECT id_ticket, id_order, subject, message, priority, status, response,
               created_at, respond_by, answered_at
        FROM dbo.support_ticket
        WHERE id_ticket = :id', [':id' => (int)$pdo->lastInsertId()]);
    return account_ticket_row($rows[0]);
}

