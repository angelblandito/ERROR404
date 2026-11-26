<?php
declare(strict_types=1);

namespace Apicore\Models;

use Apicore\Core\Database;
use PDO;
use PDOException;
use ValueError;

/**
 * Base model: generic CRUD data access shared by every entity.
 *
 * Layer: models. Entity models only declare TABLE_NAME, ID_COLUMN and COLUMNS
 * (plus any query that is specific to that entity). It must never handle HTTP.
 *
 * SQL Server dialect: names go between [brackets], pages use
 * OFFSET ... FETCH NEXT and the clock is SYSUTCDATETIME(). The store tables
 * also carry consolidation columns that only the server writes:
 *   - deleted      logical delete (DELETE marks the row, every read skips it)
 *   - updated_at   moved forward on every write
 *   - source_node  node where the row was born ('php_api' for this API)
 */
abstract class BaseModel
{
    protected const TABLE_NAME = '';      // Table of the entity (set by subclasses)
    protected const ID_COLUMN = 'Id';     // Primary key column (override if different)
    protected const COLUMNS = [];         // Writable columns (whitelist, excludes an IDENTITY primary key)

    protected const HIDDEN_COLUMNS = [];  // Columns never returned by getAll/getById (e.g. passwords)

    protected const ID_IS_IDENTITY = true;   // false: the client sends the id (catalog tables with TINYINT keys)
    protected const SOFT_DELETE = false;     // the table has a `deleted` column
    protected const HAS_UPDATED_AT = false;  // the table has an `updated_at` column
    protected const SOURCE_NODE = null;      // value for `source_node` on insert (null = no such column)
    protected const ORDER_BY = null;         // list order; null = primary key

    // Type conversions applied by publicRow(): pdo_sqlsrv returns every value as a string
    protected const INTEGER_COLUMNS = [];    // INT, TINYINT, SMALLINT, BIGINT -> int
    protected const BOOLEAN_COLUMNS = [];    // BIT -> bool
    protected const DECIMAL_COLUMNS = [];    // DECIMAL -> string (keeps precision, never a float)

    // Results of update()
    public const NOT_FOUND = 'not_found';
    public const UNCHANGED = 'unchanged';
    public const UPDATED = 'updated';

    protected PDO $pdo;

    public function __construct()
    {
        // One connection per request, shared by every model instance
        $this->pdo = Database::connection();
    }

    public function close(): void
    {
        // The connection belongs to the request, so there is nothing to release
    }

    /** [name] quoted for SQL Server. Names come from the constants, never from the client. */
    protected static function quote(string $name): string
    {
        return '[' . str_replace(']', ']]', $name) . ']';
    }

    protected static function table(): string
    {
        return static::quote(static::TABLE_NAME);
    }

    /** WHERE clause that hides logically deleted rows ('' when the table has none). */
    protected static function alive(string $glue = 'WHERE'): string
    {
        return static::SOFT_DELETE ? " $glue [deleted] = 0" : '';
    }

    /**
     * Return only the writable columns of $data (primary key removed).
     *
     * Column names are interpolated into SQL, so unknown keys are rejected
     * (raises ValueError) to prevent SQL injection through the JSON body.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filter(array $data): array
    {
        // An IDENTITY primary key is never written by the client, so drop it
        if (static::ID_IS_IDENTITY) {
            unset($data[static::ID_COLUMN]);
        }
        $unknown = array_diff(array_keys($data), static::COLUMNS);
        if ($unknown !== []) {
            throw new ValueError('Unknown field(s): ' . implode(', ', $unknown));
        }
        if ($data === []) {
            throw new ValueError('No data provided');
        }
        return $data;
    }

    /**
     * Insert a record. Returns the new id.
     *
     * SCOPE_IDENTITY() runs in the same batch: several tables have AFTER INSERT
     * triggers that write elsewhere, so @@IDENTITY could return their id instead.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->filter($data);
        if (static::SOURCE_NODE !== null) {
            $data['source_node'] = static::SOURCE_NODE;
        }
        $columns = implode(', ', array_map([static::class, 'quote'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        try {
            $sql = 'INSERT INTO ' . static::table() . " ($columns) VALUES ($placeholders)";
            if (!static::ID_IS_IDENTITY) {
                $statement = $this->pdo->prepare($sql);
                $statement->execute(self::bindable(array_values($data)));
                return (int) $data[static::ID_COLUMN];
            }
            $statement = $this->pdo->prepare(
                'SET NOCOUNT ON; ' . $sql . '; SELECT CAST(SCOPE_IDENTITY() AS INT) AS NewId;'
            );
            $statement->execute(self::bindable(array_values($data)));
            // Skip the row counts the triggers may leave before the SELECT
            while ($statement->columnCount() === 0 && $statement->nextRowset()) {
            }
            return (int) $statement->fetchColumn();
        } catch (PDOException $error) {
            self::rethrowIntegrity($error);
            throw $error;
        }
    }

    /**
     * Values ready to bind: PDO sends a bool as '' unless it is cast, and SQL
     * Server rejects that for a BIT column, so booleans become 1/0.
     *
     * @param list<mixed> $values
     * @return list<mixed>
     */
    protected static function bindable(array $values): array
    {
        return array_map(
            static fn ($value) => is_bool($value) ? (int) $value : $value,
            $values,
        );
    }

    /**
     * Remove HIDDEN_COLUMNS from a row and convert the values the driver
     * returns as strings (null passes through).
     *
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    protected function publicRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row = array_diff_key($row, array_flip(static::HIDDEN_COLUMNS));
        foreach (static::INTEGER_COLUMNS as $column) {
            if (isset($row[$column])) {
                $row[$column] = (int) $row[$column];
            }
        }
        foreach (static::BOOLEAN_COLUMNS as $column) {
            if (isset($row[$column])) {
                $row[$column] = (bool) (int) $row[$column];
            }
        }
        foreach (static::DECIMAL_COLUMNS as $column) {
            if (isset($row[$column])) {
                $row[$column] = (string) $row[$column];
            }
        }
        return $row;
    }

    /**
     * Return records of the table, optionally paginated (page starts at 1).
     *
     * @return list<array<string, mixed>>
     */
    public function getAll(?int $page = null, ?int $size = null): array
    {
        $order = static::ORDER_BY ?? static::quote(static::ID_COLUMN);
        $sql = 'SELECT * FROM ' . static::table() . static::alive() . ' ORDER BY ' . $order;
        $params = [];
        if ($size) {
            $sql .= ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
            $params = [(max($page ?? 1, 1) - 1) * $size, $size];
        }
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value, PDO::PARAM_INT);
        }
        $statement->execute();
        return array_map([$this, 'publicRow'], $statement->fetchAll());
    }

    /**
     * Every primary key of the store is an integer: "/customer/abc" is simply
     * not found, instead of a conversion error from SQL Server.
     */
    protected static function validId(int|string $recordId): bool
    {
        return is_int($recordId) || preg_match('/^\d{1,10}$/', $recordId) === 1;
    }

    /**
     * Return one record, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int|string $recordId): ?array
    {
        if (!static::validId($recordId)) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . static::table() . ' WHERE ' . static::quote(static::ID_COLUMN) . ' = ?'
            . static::alive('AND')
        );
        $statement->execute([$recordId]);
        $row = $statement->fetch();
        return $this->publicRow($row === false ? null : $row);
    }

    /**
     * Update a record by id.
     *
     * Returns 'not_found' if the id does not exist, 'unchanged' if the sent
     * values equal the stored ones, or 'updated' after writing the changes.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $recordId, array $data): string
    {
        // The key of a row is not changed through its own URL
        unset($data[static::ID_COLUMN]);
        $data = $this->filter($data);
        if (!static::validId($recordId)) {
            return self::NOT_FOUND;
        }
        $columns = array_keys($data);
        $idColumn = static::quote(static::ID_COLUMN);
        try {
            // Read the current values of only the columns being modified
            $statement = $this->pdo->prepare(
                'SELECT ' . implode(', ', array_map([static::class, 'quote'], $columns)) . ' FROM ' . static::table()
                . " WHERE $idColumn = ?" . static::alive('AND')
            );
            $statement->execute([$recordId]);
            $current = $statement->fetch();
            if ($current === false) {
                return self::NOT_FOUND;
            }
            // Loose comparison: the driver returns "1" or "650.00" where the body sent 1 or 650
            if ($current == $data) {
                return self::UNCHANGED;
            }

            $sets = array_map(static fn (string $key): string => static::quote($key) . ' = ?', $columns);
            if (static::HAS_UPDATED_AT) {
                $sets[] = '[updated_at] = SYSUTCDATETIME()';
            }
            $statement = $this->pdo->prepare(
                'UPDATE ' . static::table() . ' SET ' . implode(', ', $sets) . " WHERE $idColumn = ?"
            );
            $statement->execute([...self::bindable(array_values($data)), $recordId]);
            return self::UPDATED;
        } catch (PDOException $error) {
            self::rethrowIntegrity($error);
            throw $error;
        }
    }

    /**
     * Delete a record by id. Returns true if a row was deleted, false if the id
     * does not exist. Tables with a `deleted` column only mark the row, so the
     * branches and the Azure copy receive the delete in the next sync.
     */
    public function delete(int|string $recordId): bool
    {
        if (!static::validId($recordId)) {
            return false;
        }
        $idColumn = static::quote(static::ID_COLUMN);
        if (static::SOFT_DELETE) {
            $sql = 'UPDATE ' . static::table() . ' SET [deleted] = 1'
                . (static::HAS_UPDATED_AT ? ', [updated_at] = SYSUTCDATETIME()' : '')
                . " WHERE $idColumn = ? AND [deleted] = 0";
        } else {
            $sql = 'DELETE FROM ' . static::table() . " WHERE $idColumn = ?";
        }
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([$recordId]);
            return $statement->rowCount() > 0;
        } catch (PDOException $error) {
            self::rethrowIntegrity($error);
            throw $error;
        }
    }

    /**
     * SQLSTATE 23000 is a constraint violation. SQL Server numbers: 2627/2601 a
     * duplicated key, 547 a foreign key or CHECK constraint.
     */
    private static function rethrowIntegrity(PDOException $error): void
    {
        $number = (int) ($error->errorInfo[1] ?? 0);
        if (in_array($number, [2627, 2601], true)) {
            throw new ValueError('Record already exists');  // UNIQUE key violated
        }
        if ($number === 547) {
            throw new ValueError('Invalid reference or value rejected by a constraint');
        }
        if ($error->getCode() === '23000') {
            throw new ValueError('Record already exists');
        }
    }
}
