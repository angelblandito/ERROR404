<?php
/**
 * Runner de pruebas unitarias: sin servidor ni base de datos.
 * Uso: php tests/run.php   (codigo de salida 0 = todo bien)
 *
 * Traduccion de los tests unitarios del profe (unittest). Cada archivo test_*.php define funciones
 * test_*() que lanzan RuntimeException si fallan.
 */
require_once __DIR__ . '/../bootstrap.php';

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($message ?: 'assert_same') . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_true(bool $value, string $message = ''): void
{
    if (!$value) {
        throw new RuntimeException($message ?: 'assert_true failed');
    }
}

/** Verifica que $fn lance exactamente $class (o una subclase). */
function assert_throws(string $class, callable $fn, string $message = ''): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        if ($error instanceof $class) {
            return;
        }
        throw new RuntimeException(($message ?: 'assert_throws') . ': wrong exception ' . $error::class . ': ' . $error->getMessage());
    }
    throw new RuntimeException(($message ?: 'assert_throws') . ": no exception of $class");
}

$files = glob(__DIR__ . '/test_*.php') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    require_once $file;
}

$passed = 0;
$failures = 0;
foreach (get_defined_functions()['user'] as $name) {
    if (!str_starts_with($name, 'test_')) {
        continue;
    }
    try {
        $name();
        $passed++;
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL $name: {$error->getMessage()}\n";
    }
}
echo "$passed passed, $failures failed\n";
exit($failures === 0 ? 0 : 1);
