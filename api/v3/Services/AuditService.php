<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Logger;
use PDO;
use Throwable;

/**
 * Audit log of the admin panel (table dbo.audit_log, already in db_jam.sql).
 *
 * The actor of the panel is a customer with an Administrador account, so it is stored with
 * actor_role = 'store_admin', id_actor = id_customer and id_store = online store.
 * If the audit log fails, the administrator's action goes on and the error is logged.
 * Layer: services.
 */
final class AuditService
{
    public const ROLE = 'store_admin';

    public const MAX_ROWS = 500;

    public static function record(PDO $pdo, array $admin, string $action, string $entity, int $entityId, array $detail = []): void
    {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO dbo.audit_log (id_store, id_actor, actor_role, action, entity, entity_id, detail, source_node)
                VALUES (:id_store, :id_actor, :role, :action, :entity, :entity_id, :detail, :source_node)');
            $stmt->bindValue(':id_store', StoreService::ONLINE_ID, PDO::PARAM_INT);
            $stmt->bindValue(':id_actor', (int)($admin['id_customer'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':role', self::ROLE, PDO::PARAM_STR);
            $stmt->bindValue(':action', substr($action, 0, 50), PDO::PARAM_STR);
            $stmt->bindValue(':entity', substr($entity, 0, 50), PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->bindValue(':detail', $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null, $detail ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':source_node', 'web_node', PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $error) {
            Logger::exception('[bitácora] ' . $error->getMessage());
        }
    }

    /* Latest entries, newest first. Optional filters: action and entity. */
    public static function list(PDO $pdo, ?string $action, ?string $entity): array
    {
        $where = [];
        $params = [];
        if ($action !== null) {
            $where[] = 'a.action = :action';
            $params[':action'] = $action;
        }
        if ($entity !== null) {
            $where[] = 'a.entity = :entity';
            $params[':entity'] = $entity;
        }
        $rows = StoreService::rows($pdo, '
            SELECT TOP (' . self::MAX_ROWS . ') a.id_audit, a.id_store, a.id_actor, a.actor_role, a.action, a.entity,
                   a.entity_id, a.detail, a.source_node, a.created_at,
                   CASE WHEN a.actor_role = \'' . self::ROLE . '\'
                        THEN c.first_name + \' \' + c.last_name_paternal END AS actor_name
            FROM dbo.audit_log AS a
            LEFT JOIN dbo.customer AS c ON c.id_customer = a.id_actor AND a.actor_role = \'' . self::ROLE . '\'
            ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
            ORDER BY a.id_audit DESC', $params);
        return array_map(function ($r) {
            $r['id_audit'] = (int)$r['id_audit'];
            $r['id_store'] = (int)$r['id_store'];
            $r['id_actor'] = (int)$r['id_actor'];
            $r['entity_id'] = (int)$r['entity_id'];
            $r['detail'] = $r['detail'] !== null ? json_decode($r['detail'], true) : null;
            return $r;
        }, $rows);
    }
}
