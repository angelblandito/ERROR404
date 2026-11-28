<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use Apicore\Core\Database;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Distributed transactions (unit 4): stock transfer between branches, the
 * transaction log and the global checkpoint.
 *
 *   POST /distributed/transfer      Moves stock from one branch to another in ONE
 *                                   transaction over three databases (central +
 *                                   both fragments): dist.sp_traspasar_inventario
 *   GET  /distributed/transactions  Last transactions with their state, the last
 *                                   global checkpoint and the current lock waits
 *   POST /distributed/checkpoint    Global checkpoint coordinated by the central
 *                                   node: dist.sp_punto_verificacion_global
 *
 * The concurrency control (2PL with UPDLOCK/HOLDLOCK, fixed lock order, deadlock
 * retry) and the commit over the three databases (SQL Server's internal 2PC) live
 * in the stored procedures of API_JAM/distribuida/13_manejo_transacciones.sql.
 *
 * Layer: services. Business rules and SQL, no HTTP. Administrators only (the
 * controller checks it).
 */
final class TransactionService
{
    /** Branches that are fragments of the distributed database. */
    public const BRANCHES = [1 => 'tijuana', 2 => 'cdmx', 4 => 'online'];

    public const MAX_UNITS = 10000;

    /**
     * Check the body of a transfer before touching the database.
     *
     * @param array<string, mixed> $body
     * @return array{id_variant: int, from_store: int, to_store: int, quantity: int}
     */
    public static function transferInput(array $body): array
    {
        $int = static function (string $key) use ($body): ?int {
            $value = $body[$key] ?? null;
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1) {
                return (int) trim($value);
            }
            return null;
        };

        $idVariant = $int('id_variant');
        $from = $int('from_store');
        $to = $int('to_store');
        $quantity = $int('quantity');

        if ($idVariant === null || $idVariant <= 0) {
            throw new AppError('Indica la talla y color a traspasar (id_variant).', 400);
        }
        if ($from === null || $to === null || !isset(self::BRANCHES[$from]) || !isset(self::BRANCHES[$to])) {
            throw new AppError('Las sucursales deben ser 1 (Tijuana), 2 (CDMX) o 4 (Tienda en línea).', 400);
        }
        if ($from === $to) {
            throw new AppError('La sucursal de origen y la de destino deben ser distintas.', 400);
        }
        if ($quantity === null || $quantity < 1 || $quantity > self::MAX_UNITS) {
            throw new AppError('La cantidad debe estar entre 1 y 10,000 piezas.', 400);
        }

        return ['id_variant' => $idVariant, 'from_store' => $from, 'to_store' => $to, 'quantity' => $quantity];
    }

    /**
     * Run the distributed transaction. A business stop (no stock, a branch
     * offline...) comes back as a 409 with the procedure's message.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function transfer(array $body): array
    {
        $data = self::transferInput($body);
        $pdo = Database::node('central');
        try {
            $stmt = $pdo->prepare('EXEC dist.sp_traspasar_inventario
                @id_variant = :id_variant, @desde_store = :desde, @hacia_store = :hacia, @cantidad = :cantidad');
            $stmt->bindValue(':id_variant', $data['id_variant'], PDO::PARAM_INT);
            $stmt->bindValue(':desde', $data['from_store'], PDO::PARAM_INT);
            $stmt->bindValue(':hacia', $data['to_store'], PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $data['quantity'], PDO::PARAM_INT);
            $stmt->execute();
            $row = self::firstRow($stmt);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) >= 50000) {
                throw AppError::fromDatabase($error);
            }
            throw $error;
        }
        if ($row === null) {
            throw new AppError('El traspaso no devolvió resultado.', 500);
        }

        return [
            'id_transaction' => (int) $row['id_transaction'],
            'state' => $row['estado'],
            'attempts' => (int) $row['intentos'],
            'participants' => $row['participantes'],
            'from' => ['id_store' => $data['from_store'], 'node' => self::BRANCHES[$data['from_store']], 'stock' => (int) $row['stock_origen']],
            'to' => ['id_store' => $data['to_store'], 'node' => self::BRANCHES[$data['to_store']], 'stock' => (int) $row['stock_destino']],
            'quantity' => $data['quantity'],
            'id_variant' => $data['id_variant'],
        ];
    }

    /**
     * Last transactions, the last global checkpoint and the current lock waits.
     *
     * @return array<string, mixed>
     */
    public static function overview(int $limit = 30): array
    {
        $pdo = Database::node('central');
        $limit = max(1, min(100, $limit));

        $transactions = array_map(static fn (array $r): array => [
            'id_transaction' => (int) $r['id_transaction'],
            'type' => $r['tx_type'],
            'state' => $r['state'],
            'participants' => $r['participants'],
            'detail' => $r['detail'],
            'attempts' => (int) $r['attempts'],
            'error_number' => $r['error_number'] === null ? null : (int) $r['error_number'],
            'error_message' => $r['error_message'],
            'started_at' => $r['started_at'],
            'ended_at' => $r['ended_at'],
        ], DistributedService::query($pdo, "
            SELECT TOP ($limit) id_transaction, tx_type, state, participants, detail, attempts,
                   error_number, error_message,
                   CONVERT(VARCHAR(23), started_at, 126) AS started_at,
                   CONVERT(VARCHAR(23), ended_at, 126) AS ended_at
            FROM dist.transaction_log
            ORDER BY id_transaction DESC"));

        $checkpoint = DistributedService::query($pdo, "
            SELECT c.node_name, c.db_name, c.status, c.checkpoint_lsn, c.log_since_checkpoint_mb, c.message,
                   l.id_transaction, l.state, CONVERT(VARCHAR(23), l.ended_at, 126) AS ended_at
            FROM dist.checkpoint_log AS c
            JOIN dist.transaction_log AS l ON l.id_transaction = c.id_transaction
            WHERE c.id_transaction = (SELECT MAX(id_transaction) FROM dist.checkpoint_log)
            ORDER BY c.id_checkpoint");

        /* Wait-for graph: needs VIEW SERVER STATE (granted to jam_api by script 13) */
        try {
            $waits = DistributedService::query($pdo, 'SELECT * FROM dist.vw_grafo_espera');
        } catch (PDOException $error) {
            $waits = null;
        }

        $states = ['activa' => 0, 'confirmada' => 0, 'abortada' => 0];
        foreach ($transactions as $tx) {
            $states[$tx['state']] = ($states[$tx['state']] ?? 0) + 1;
        }

        return [
            'transactions' => $transactions,
            'states' => $states,
            'last_checkpoint' => $checkpoint === [] ? null : [
                'id_transaction' => (int) $checkpoint[0]['id_transaction'],
                'state' => $checkpoint[0]['state'],
                'ended_at' => $checkpoint[0]['ended_at'],
                'nodes' => array_map(static fn (array $r): array => [
                    'node' => $r['node_name'],
                    'database' => $r['db_name'],
                    'status' => $r['status'],
                    'checkpoint_lsn' => $r['checkpoint_lsn'],
                    'log_since_checkpoint_mb' => $r['log_since_checkpoint_mb'] === null ? null : (float) $r['log_since_checkpoint_mb'],
                    'message' => $r['message'],
                ], $checkpoint),
            ],
            'lock_waits' => $waits,
        ];
    }

    /**
     * Global checkpoint: the central node asks every node for a CHECKPOINT.
     *
     * @return array<string, mixed>
     */
    public static function checkpoint(): array
    {
        $pdo = Database::node('central');
        $stmt = $pdo->query('EXEC dist.sp_punto_verificacion_global');
        $rows = [];
        do {
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            }
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
        if ($rows === []) {
            throw new AppError('El punto de verificación no devolvió resultado.', 500);
        }

        return [
            'id_transaction' => (int) $rows[0]['id_transaction'],
            'state' => $rows[0]['estado_global'],
            'nodes' => array_map(static fn (array $r): array => [
                'node' => $r['nodo'],
                'database' => $r['base'],
                'status' => $r['estado'],
                'checkpoint_lsn' => $r['checkpoint_lsn'],
                'log_since_checkpoint_mb' => $r['bitacora_desde_checkpoint_mb'] === null ? null : (float) $r['bitacora_desde_checkpoint_mb'],
                'message' => $r['mensaje'],
            ], $rows),
        ];
    }

    /** First row of the first result set of a procedure (skips the empty ones). */
    private static function firstRow(PDOStatement $stmt): ?array
    {
        $row = null;
        do {
            if ($stmt->columnCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                break;
            }
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
        return $row;
    }
}
