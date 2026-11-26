<?php
/**
 * Generador de la API JAM. Lee el SQL de la base y escribe, por cada tabla, su schema, modelo,
 * controller y router en schemas/, models/, controllers/ y routers/. Asi cada entidad sale del esquema real y no de archivos escritos a mano.
 *
 * Uso (desde la carpeta api/v2):   php build.php
 *
 * Solo borra y reescribe archivos que llevan la marca GENERADO. Los escritos a mano
 * (auth_*, services, bootstrap, index) no se tocan. Las tablas que no tienen API se listan al final.
 */

const MARCA = '// GENERADO por build.php. No editar a mano: cambia el SQL o las reglas y vuelve a generar.';

/** Esquema real de la base: el mismo script que crea JAM_db. */
const FUENTES = [
    __DIR__ . '/../../API_JAM/db_jam.sql',
];

const SALIDA = __DIR__;

/** Tablas sin CRUD generico: sesiones, credenciales, bitacoras o con rutas propias en routers. */
const SIN_API = [
    'customer_credential', 'customer_session', 'password_reset', 'audit_log', 'message_log',
    'verification_code', 'email_outbox', 'site_visit', 'search_log', 'app_install', 'mirror_sync',
    'order_status_history',
];

/** Orden de la lista cuando no es por la llave primaria. */
const ORDEN = ['season_theme' => '[priority] DESC, [id_theme]'];

/** Tablas cuya lectura (GET) es publica: la tienda las pide sin sesion. */
const LECTURA_PUBLICA = ['promotion', 'state', 'season_theme'];

/** Columnas que escribe el servidor, nunca el cliente. */
const CAMPOS_SERVIDOR = ['source_node', 'updated_at', 'deleted', 'created_at'];

const TIPOS = [
    'VARCHAR' => 'string', 'CHAR' => 'string', 'NVARCHAR' => 'string',
    'INT' => 'integer', 'TINYINT' => 'integer', 'SMALLINT' => 'integer', 'BIGINT' => 'integer',
    'DECIMAL' => 'number', 'NUMERIC' => 'number', 'FLOAT' => 'number',
    'BIT' => 'boolean', 'DATE' => 'date', 'DATETIME' => 'datetime', 'DATETIME2' => 'datetime', 'TIME' => 'time',
];

/** Tipos de SQL Server que reconoce el lector (los que no estan en TIPOS detienen la generacion). */
const TIPOS_SQL = [
    'VARCHAR', 'CHAR', 'NVARCHAR', 'NCHAR', 'INT', 'TINYINT', 'SMALLINT', 'BIGINT', 'DECIMAL', 'NUMERIC',
    'FLOAT', 'REAL', 'MONEY', 'BIT', 'DATE', 'DATETIME', 'DATETIME2', 'TIME', 'DATETIMEOFFSET', 'SMALLDATETIME',
    'VARBINARY', 'BINARY', 'UNIQUEIDENTIFIER', 'XML', 'TEXT', 'NTEXT', 'ROWVERSION',
];

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: $message\n");
    exit(1);
}

function studly(string $snake): string
{
    return implode('', array_map('ucfirst', explode('_', $snake)));
}

function column(string $name, string $type, ?string $len, ?string $scale, string $rest): array
{
    $upper = strtoupper($type);
    $nullable = !preg_match('/NOT NULL/i', $rest);
    $identity = (bool) preg_match('/IDENTITY/i', $rest);
    $has_default = (bool) preg_match('/\bDEFAULT\b/i', $rest);
    return [
        'name' => $name,
        'kind' => TIPOS[$upper] ?? 'unknown',
        'type' => $upper,
        'len' => $len !== null ? (int) $len : null,
        'scale' => $scale !== null ? (int) $scale : null,
        'nullable' => $nullable,
        'identity' => $identity,
        'required' => !$nullable && !$has_default && !$identity,
        'enum' => null,
        'min' => null,
        'max' => null,
    ];
}

/** Lee CHECK de la columna: IN (...), BETWEEN, >= y >. */
function apply_checks(array &$columns, string $text): void
{
    preg_match_all('/CHECK \((\w+) IN \(([^)]*)\)\)/i', $text, $enums, PREG_SET_ORDER);
    foreach ($enums as $e) {
        if (isset($columns[$e[1]])) {
            preg_match_all("/'([^']*)'/", $e[2], $values);
            $columns[$e[1]]['enum'] = $values[1];
        }
    }
    preg_match_all('/CHECK \((\w+) BETWEEN (-?\d+) AND (-?\d+)\)/i', $text, $ranges, PREG_SET_ORDER);
    foreach ($ranges as $r) {
        if (isset($columns[$r[1]])) {
            $columns[$r[1]]['min'] = (int) $r[2];
            $columns[$r[1]]['max'] = (int) $r[3];
        }
    }
    preg_match_all('/CHECK \((\w+) >= (-?\d+)\)/i', $text, $mins, PREG_SET_ORDER);
    foreach ($mins as $m) {
        if (isset($columns[$m[1]]) && $columns[$m[1]]['min'] === null) {
            $columns[$m[1]]['min'] = (int) $m[2];
        }
    }
    preg_match_all('/CHECK \((\w+) > (-?\d+)\)/i', $text, $greater, PREG_SET_ORDER);
    foreach ($greater as $g) {
        if (isset($columns[$g[1]]) && $columns[$g[1]]['min'] === null) {
            $columns[$g[1]]['min'] = (int) $g[2] + 1;
        }
    }
}

/** Lee CREATE TABLE y ALTER TABLE ... ADD de todas las fuentes, en orden. */
function load_tables(): array
{
    $tables = [];
    foreach (FUENTES as $path) {
        $sql = @file_get_contents($path);
        if ($sql === false) {
            fail("no puedo leer $path");
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        preg_match_all('/CREATE TABLE (\w+) \((.*?)\n\);/s', $sql, $creates, PREG_SET_ORDER);
        foreach ($creates as $c) {
            $columns = [];
            $pk = [];
            if (preg_match('/PRIMARY KEY \(([^)]*)\)/i', $c[2], $p)) {
                $pk = array_map('trim', explode(',', $p[1]));
            }
            foreach (explode("\n", $c[2]) as $line) {
                $line = trim(rtrim(trim($line), ','));
                if ($line === '' || preg_match('/^(CONSTRAINT|PRIMARY|UNIQUE|CHECK|FOREIGN|INDEX)\b/i', $line)) {
                    continue;
                }
                // Solo cuenta como columna si el segundo token es un tipo de SQL (las restricciones
                // de varias lineas, como un CHECK largo, no son columnas)
                if (preg_match('/^(\w+)\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?(.*)$/', $line, $col)
                    && in_array(strtoupper($col[2]), TIPOS_SQL, true)) {
                    $columns[$col[1]] = column($col[1], $col[2], $col[3] ?? null, $col[4] ?? null, $col[5]);
                    if (preg_match('/PRIMARY KEY/i', $col[5])) {
                        $pk[] = $col[1];
                    }
                }
            }
            apply_checks($columns, $c[2]);
            $tables[$c[1]] = ['columns' => $columns, 'pk' => array_values(array_unique($pk))];
        }

        preg_match_all('/ALTER TABLE (\w+)\s+ADD\s+(?!CONSTRAINT\b)(\w+)\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?([^;]*);/s', $sql, $alters, PREG_SET_ORDER);
        foreach ($alters as $a) {
            if (!isset($tables[$a[1]])) {
                fail("ALTER sobre una tabla desconocida: {$a[1]}");
            }
            $tables[$a[1]]['columns'][$a[2]] = column($a[2], $a[3], $a[4] ?? null, $a[5] ?? null, $a[6]);
            apply_checks($tables[$a[1]]['columns'], $a[6]);
        }
    }
    return $tables;
}

function lit(mixed $value): string
{
    return $value === null ? 'null' : (string) $value;
}

/** Linea del schema para una columna escribible. */
function schema_line(array $c): string
{
    $field = "'{$c['name']}'";
    $req = $c['required'] ? 'true' : 'false';
    if ($c['kind'] === 'string' && $c['enum'] !== null) {
        $options = '[' . implode(', ', array_map(static fn($v) => "'$v'", $c['enum'])) . ']';
        $call = "validate_enum(\$data, $field, $options, \$partial);";
        // Si no es obligatorio (nulo o con DEFAULT), solo se valida cuando viene un valor
        return $c['required'] ? $call : "if (isset(\$data[$field])) { $call }";
    }
    switch ($c['kind']) {
        case 'string':
            return "validate_string(\$data, $field, " . lit($c['len']) . ", $req, \$partial);";
        case 'integer':
            $min = $c['min'] ?? ($c['type'] === 'TINYINT' ? 0 : null);
            $max = $c['max'] ?? ($c['type'] === 'TINYINT' ? 255 : null);
            return "validate_integer(\$data, $field, " . lit($min) . ', ' . lit($max) . ", $req, \$partial);";
        case 'number':
            $scale = $c['scale'] ?? 0;
            $max = $c['len'] !== null ? pow(10, $c['len'] - $scale) - pow(10, -$scale) : null;
            $min = $c['min'];
            $fmt = static fn($v) => $v === null ? 'null' : sprintf('%.' . $scale . 'F', $v);
            return "validate_number(\$data, $field, " . $fmt($min) . ', ' . $fmt($max) . ", $req, \$partial);";
        case 'boolean':
            return "validate_boolean(\$data, $field, $req, \$partial);";
        case 'date':
            return "validate_date(\$data, $field, $req, \$partial);";
        case 'datetime':
            return "validate_datetime(\$data, $field, $req, \$partial);";
        case 'time':
            return "validate_time(\$data, $field, $req, \$partial);";
    }
    fail("tipo sin validador: {$c['name']} ({$c['type']})");
}

function quoted_list(array $items, string $indent): string
{
    return implode(",\n", array_map(static fn($i) => "$indent'$i'", $items));
}

function render_schema(string $class, array $writable, bool $has_password, bool $password_required): string
{
    $lines = array_map(static fn($c) => '        ' . schema_line($c), $writable);
    if ($has_password) {
        $req = $password_required ? 'true' : 'false';
        $lines[] = "        validate_string(\$data, 'password', 255, $req, \$partial);";
    }
    $allowed = array_map(static fn($c) => $c['name'], $writable);
    if ($has_password) {
        $allowed[] = 'password';
    }
    return "<?php\n" . MARCA . "\n\n"
        . "/** Reglas de entrada de $class. Traduccion de schemas/<entidad>_schema.py del profe. */\n"
        . "final class {$class}Schema\n{\n"
        . "    public static function validate(array \$data, bool \$partial = false): void\n    {\n"
        . "        if (\$partial && \$data === []) {\n"
        . "            throw new ValueError('No fields to update');\n        }\n"
        . "        reject_unknown_fields(\$data, [\n" . quoted_list($allowed, '            ') . "\n        ]);\n"
        . implode("\n", $lines) . "\n    }\n}\n";
}

function render_model(string $class, string $table, string $id, array $readable, bool $soft, bool $updated, bool $source): string
{
    return "<?php\n" . MARCA . "\n\n"
        . "/** Acceso a la tabla $table. Traduccion de models/<entidad>.py del profe. */\n"
        . "final class {$class}Model extends BaseModel\n{\n"
        . "    public const TABLE_NAME = '$table';\n"
        . "    public const ID_COLUMN = '$id';\n"
        . (isset(ORDEN[$table]) ? "    public const ORDER_BY = '" . ORDEN[$table] . "';\n" : '')
        . '    public const HAS_SOFT_DELETE = ' . ($soft ? 'true' : 'false') . ";\n"
        . '    public const HAS_UPDATED_AT = ' . ($updated ? 'true' : 'false') . ";\n"
        . '    public const SOURCE_NODE = ' . ($source ? "'php_api'" : 'null') . ";\n"
        . "    public const READABLE = [\n" . quoted_list($readable, '        ') . "\n    ];\n}\n";
}

function render_controller(string $class, string $table, bool $has_password): string
{
    $password = $has_password ? "        Policy::hash_password(\$data);\n" : '';
    return "<?php\n" . MARCA . "\n\n"
        . "/** Controller de $table. Traduccion de controllers/<entidad>_controller.py del profe. */\n"
        . "final class {$class}Controller extends BaseController\n{\n"
        . "    public const MODEL_CLASS = {$class}Model::class;\n"
        . "    public const SCHEMA = {$class}Schema::class;\n\n"
        . "    protected function allow(string \$action): bool\n    {\n"
        . "        return Policy::allowed(\$this->auth_user, '$table', \$action);\n    }\n\n"
        . "    protected function scope(): ?array\n    {\n"
        . "        return Policy::scope(\$this->auth_user, '$table');\n    }\n\n"
        . "    protected function prepare(array &\$data, string \$action): void\n    {\n"
        . "        Policy::prepare(\$this->auth_user, '$table', \$data, \$action);\n"
        . $password
        . "    }\n\n"
        . "    protected function authorize(string \$action, array \$data, ?int \$id): void\n    {\n"
        . "        Policy::authorize(\$this->auth_user, '$table', \$action, \$data);\n    }\n}\n";
}

function render_router(string $class, string $table, bool $public_read): string
{
    $label = ucfirst(str_replace('_', ' ', $table));
    $public = $public_read ? ", false, ['GET']" : ', false';
    return "<?php\n" . MARCA . "\n\n"
        . "/** Rutas de $table. Traduccion de routers/<entidad>_router.py del profe. */\n"
        . "final class {$class}Router\n{\n"
        . "    public static function register_routes(Router \$router): void\n    {\n"
        . "        \$router->add_crud_routes('/$table', {$class}Controller::class, '$label'$public);\n"
        . "    }\n}\n";
}

function clean(string $folder): void
{
    foreach (glob("$folder/*.php") ?: [] as $file) {
        if (str_contains((string) file_get_contents($file), MARCA)) {
            unlink($file);
        }
    }
}

// ---------------------------------------------------------------------------

$tables = load_tables();
foreach (['schemas', 'models', 'controllers', 'routers'] as $folder) {
    @mkdir(SALIDA . "/$folder", 0777, true);
    clean(SALIDA . "/$folder");
}

$generadas = [];
$sin_api = [];
foreach ($tables as $name => $t) {
    if (in_array($name, SIN_API, true)) {
        $sin_api[] = "$name (interna o llave compuesta)";
        continue;
    }
    $pk = $t['pk'];
    if (count($pk) !== 1 || !isset($t['columns'][$pk[0]]) || $t['columns'][$pk[0]]['kind'] !== 'integer') {
        $sin_api[] = "$name (llave primaria no simple)";
        continue;
    }

    $id = $pk[0];
    $columns = $t['columns'];
    $writable = [];
    $readable = [];
    foreach ($columns as $col => $c) {
        // row_version (ROWVERSION) lo mantiene SQL Server: ni se lee ni se escribe por la API
        if ($col === 'password_hash' || $col === 'source_node' || $col === 'deleted' || $c['type'] === 'ROWVERSION') {
            continue;
        }
        $readable[] = $col;
        if ($col !== $id && !in_array($col, CAMPOS_SERVIDOR, true)) {
            if ($c['kind'] === 'unknown') {
                fail("tipo desconocido en $name.$col: {$c['type']}");
            }
            $writable[] = $c;
        }
    }
    $has_password = isset($columns['password_hash']);
    $password_required = $has_password && $columns['password_hash']['required'];
    $class = studly($name);

    file_put_contents(SALIDA . "/schemas/{$name}_schema.php", render_schema($class, $writable, $has_password, $password_required));
    file_put_contents(SALIDA . "/models/{$name}_model.php", render_model(
        $class, $name, $id, $readable, isset($columns['deleted']), isset($columns['updated_at']), isset($columns['source_node'])
    ));
    file_put_contents(SALIDA . "/controllers/{$name}_controller.php", render_controller($class, $name, $has_password));
    file_put_contents(SALIDA . "/routers/{$name}_router.php", render_router($class, $name, in_array($name, LECTURA_PUBLICA, true)));
    $generadas[] = $name;
}

echo 'Tablas generadas (' . count($generadas) . "): " . implode(', ', $generadas) . "\n";
echo "Sin API:\n  - " . implode("\n  - ", $sin_api) . "\n";
