<?php
/**
 * Modelo base. Traduccion de models/base_model.py del profe.
 *
 * NOTA: base_model.py no llego en los archivos subidos. Esta clase se reconstruyo para cumplir
 * exactamente lo que llama base_controller.py: create, get_all, get_by_id, update (con los
 * resultados NOT_FOUND / UNCHANGED / UPDATED), delete y close.
 *
 * Cada tabla define sus constantes (las genera build.php). Layer: models. Solo SQL; sin reglas de negocio.
 */
abstract class BaseModel
{
    public const TABLE_NAME = '';
    public const ID_COLUMN = '';
    /** Orden de la lista; vacio = por la llave primaria. */
    public const ORDER_BY = '';
    public const HAS_SOFT_DELETE = false;
    public const HAS_UPDATED_AT = false;
    public const SOURCE_NODE = null;
    public const READABLE = [];
    public const SCHEMA = null;

    /** Resultados de update() que el router traduce a HTTP (igual que el profe). */
    public const NOT_FOUND = 'NOT_FOUND';
    public const UNCHANGED = 'UNCHANGED';
    public const UPDATED = 'UPDATED';

    /**
     * Alcance por cliente: [columna, valor] o null. Lo fija el controller antes de cada operacion
     * (un cliente solo ve y cambia sus propias filas).
     */
    public ?array $scope = null;

    public function __construct(protected PDO $db)
    {
    }

    /** La conexion la cierra Database al terminar la peticion. */
    public function close(): void
    {
    }

    /** Condiciones comunes: borrado logico y alcance. Devuelve [WHERE, parametros]. */
    protected function where(): array
    {
        $conditions = [];
        $params = [];
        if (static::HAS_SOFT_DELETE) {
            $conditions[] = '[deleted] = 0';
        }
        if ($this->scope !== null) {
            $conditions[] = '[' . $this->scope[0] . '] = ?';
            $params[] = $this->scope[1];
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function select_list(): string
    {
        return implode(', ', array_map(static fn(string $c) => "[$c]", static::READABLE));
    }

    /** Lista. Sin page/size devuelve todas las filas (igual que el profe); con ellos, pagina. */
    public function get_all(?int $page = null, ?int $size = null): array
    {
        [$where, $params] = $this->where();
        $sql = 'SELECT ' . $this->select_list() . ' FROM [' . static::TABLE_NAME . ']' . $where
            . ' ORDER BY ' . (static::ORDER_BY !== '' ? static::ORDER_BY : '[' . static::ID_COLUMN . ']');
        if ($page !== null && $size !== null) {
            $sql .= ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
            $params[] = ($page - 1) * $size;
            $params[] = $size;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function get_by_id(int $id): ?array
    {
        [$where, $params] = $this->where();
        $where = ($where === '' ? ' WHERE ' : $where . ' AND ') . '[' . static::ID_COLUMN . '] = ?';
        $params[] = $id;
        $stmt = $this->db->prepare('SELECT ' . $this->select_list() . ' FROM [' . static::TABLE_NAME . ']' . $where);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Inserta una fila validada. Devuelve el id generado (OUTPUT INSERTED). */
    public function create(array $data): int
    {
        if (static::SOURCE_NODE !== null) {
            $data['source_node'] = static::SOURCE_NODE;
        }
        $columns = array_keys($data);
        $stmt = $this->db->prepare(
            'INSERT INTO [' . static::TABLE_NAME . '] (' . implode(', ', array_map(static fn($c) => "[$c]", $columns)) . ')'
            . ' OUTPUT INSERTED.[' . static::ID_COLUMN . ']'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $stmt->execute(array_values($data));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Normaliza un valor para comparar contra lo que devuelve la base: bit 0/1 o true/false,
     * decimales "650.00" o 650 y null o cadena vacia.
     */
    public static function normalize_value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            $number = (float) $value;
            return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
        }
        return (string) $value;
    }

    /**
     * Actualiza. Devuelve NOT_FOUND si no hay fila visible, UNCHANGED si ningun valor cambia,
     * o UPDATED.
     */
    public function update(int $id, array $data): string
    {
        $current = $this->get_by_id($id);
        if ($current === null) {
            return self::NOT_FOUND;
        }
        $changed = false;
        foreach ($data as $field => $value) {
            if (self::normalize_value($current[$field] ?? null) !== self::normalize_value($value)) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            return self::UNCHANGED;
        }

        $sets = array_map(static fn($c) => "[$c] = ?", array_keys($data));
        $params = array_values($data);
        if (static::HAS_UPDATED_AT) {
            $sets[] = '[updated_at] = GETDATE()';
        }
        [$where, $where_params] = $this->where();
        $where = ($where === '' ? ' WHERE ' : $where . ' AND ') . '[' . static::ID_COLUMN . '] = ?';
        $stmt = $this->db->prepare('UPDATE [' . static::TABLE_NAME . '] SET ' . implode(', ', $sets) . $where);
        $stmt->execute([...$params, ...$where_params, $id]);
        return self::UPDATED;
    }

    /** Borrado logico si la tabla tiene columna deleted; si no, DELETE. Devuelve true si borro algo. */
    public function delete(int $id): bool
    {
        [$where, $params] = $this->where();
        $where = ($where === '' ? ' WHERE ' : $where . ' AND ') . '[' . static::ID_COLUMN . '] = ?';
        $params[] = $id;
        if (static::HAS_SOFT_DELETE) {
            $sql = 'UPDATE [' . static::TABLE_NAME . '] SET [deleted] = 1'
                . (static::HAS_UPDATED_AT ? ', [updated_at] = GETDATE()' : '') . $where;
        } else {
            $sql = 'DELETE FROM [' . static::TABLE_NAME . ']' . $where;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
