<?php
declare(strict_types=1);

namespace Apicore\Tests;

use Apicore\Core\AppError;
use Apicore\Core\Database;
use Apicore\Core\Router;
use Apicore\Middlewares\SyncMiddleware;
use Apicore\Routers\RouterRegistry;
use Apicore\Services\TransactionService;
use PDOException;

/**
 * Unidad 4: reintento ante interbloqueo, validación del traspaso entre
 * sucursales, acceso a las rutas de transacciones y sintaxis del script 13.
 */
final class TransaccionesTest extends TestCase
{
    private static function sqlError(int $number, string $state = 'HY000'): PDOException
    {
        $error = new PDOException('SQLSTATE[' . $state . ']: error ' . $number);
        $error->errorInfo = [$state, $number, 'error ' . $number];
        return $error;
    }

    /* ---------------- Reintento ante interbloqueo (4.2.4) ---------------- */

    public function testReintentaCuandoEsVictimaDeUnInterbloqueo(): void
    {
        $esperas = [];
        $intentos = 0;
        $resultado = Database::retryOnDeadlock(static function (int $intento) use (&$intentos): string {
            $intentos = $intento;
            if ($intento < 3) {
                throw self::sqlError(1205, '40001');
            }
            return 'confirmada';
        }, 3, 50, static function (int $ms) use (&$esperas): void {
            $esperas[] = $ms;
        });

        $this->assertSame('confirmada', $resultado);
        $this->assertSame(3, $intentos);
        $this->assertSame([50, 100], $esperas);   // espera creciente
    }

    public function testSeRindeDespuesDelUltimoIntento(): void
    {
        $esperas = [];
        $intentos = 0;
        $error = $this->assertThrows(PDOException::class, static function () use (&$intentos, &$esperas): void {
            Database::retryOnDeadlock(static function (int $intento) use (&$intentos): never {
                $intentos = $intento;
                throw self::sqlError(1205, '40001');
            }, 3, 50, static function (int $ms) use (&$esperas): void {
                $esperas[] = $ms;
            });
        });
        $this->assertSame(1205, $error->errorInfo[1]);
        $this->assertSame(3, $intentos);
        $this->assertSame([50, 100], $esperas);
    }

    public function testOtrosErroresNoSeReintentan(): void
    {
        $intentos = 0;
        $this->assertThrows(PDOException::class, static function () use (&$intentos): void {
            Database::retryOnDeadlock(static function (int $intento) use (&$intentos): never {
                $intentos = $intento;
                throw self::sqlError(547);   // CHECK: reintentar no lo arreglaría
            }, 3, 50, static function (int $ms): void {
            });
        });
        $this->assertSame(1, $intentos);

        $this->assertTrue(Database::isDeadlock(self::sqlError(1205)));
        $this->assertTrue(Database::isDeadlock(self::sqlError(0, '40001')));
        $this->assertFalse(Database::isDeadlock(self::sqlError(50125)));
    }

    /* ---------------- Traspaso entre sucursales ---------------- */

    public function testElTraspasoValidaAntesDeTocarLaBase(): void
    {
        $ok = TransactionService::transferInput(['id_variant' => '15', 'from_store' => 1, 'to_store' => '2', 'quantity' => 3]);
        $this->assertSame(['id_variant' => 15, 'from_store' => 1, 'to_store' => 2, 'quantity' => 3], $ok);

        $casos = [
            'sin variante' => ['from_store' => 1, 'to_store' => 2, 'quantity' => 1],
            'misma sucursal' => ['id_variant' => 1, 'from_store' => 2, 'to_store' => 2, 'quantity' => 1],
            'sucursal que no es fragmento' => ['id_variant' => 1, 'from_store' => 5, 'to_store' => 2, 'quantity' => 1],
            'cantidad cero' => ['id_variant' => 1, 'from_store' => 1, 'to_store' => 2, 'quantity' => 0],
            'cantidad negativa' => ['id_variant' => 1, 'from_store' => 1, 'to_store' => 2, 'quantity' => -4],
            'demasiadas piezas' => ['id_variant' => 1, 'from_store' => 1, 'to_store' => 2, 'quantity' => 10001],
            'texto en la cantidad' => ['id_variant' => 1, 'from_store' => 1, 'to_store' => 2, 'quantity' => "1; DROP TABLE x"],
        ];
        foreach ($casos as $caso => $body) {
            $error = $this->assertThrows(AppError::class, static fn () => TransactionService::transferInput($body), $caso);
            $this->assertSame(400, $error->statusCode(), $caso);
        }
    }

    /* ---------------- Rutas ---------------- */

    public function testRutasDeTransaccionesSoloConSesion(): void
    {
        $router = new Router();
        RouterRegistry::registerAll($router);
        foreach ([['POST', '/distributed/transfer'], ['GET', '/distributed/transactions'], ['POST', '/distributed/checkpoint']] as [$method, $path]) {
            $this->assertSame(false, $router->routePublic($method, $path), $method . ' ' . $path);
            $error = $this->assertThrows(AppError::class, static fn () => $router->dispatch($method, $path, '{}'));
            $this->assertSame(401, $error->statusCode(), $method . ' ' . $path);
        }
    }

    public function testElTraspasoSincronizaYElCheckpointNo(): void
    {
        $this->assertTrue(SyncMiddleware::shouldSync('POST', '/distributed/transfer', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/distributed/checkpoint', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('GET', '/distributed/transactions', 200));
    }

    /* ---------------- Script 13 ---------------- */

    public function testElScriptAplicaLosConceptosDeLaUnidad(): void
    {
        $file = dirname(__DIR__, 3) . '/API_JAM/distribuida/13_manejo_transacciones.sql';
        $this->assertTrue(is_file($file), 'falta ' . $file);
        $sql = (string) file_get_contents($file);
        foreach ([
            'READ_COMMITTED_SNAPSHOT ON',        // MVCC
            'ALLOW_SNAPSHOT_ISOLATION ON',
            'WITH (UPDLOCK, HOLDLOCK, ROWLOCK)', // 2PL estricto
            'SET DEADLOCK_PRIORITY LOW',         // elección de víctima
            '@error = 1205',                     // reintento ante interbloqueo
            'SET XACT_ABORT ON',                 // atomicidad
            'CHECKPOINT',                        // punto de verificación
            'sys.dm_db_log_stats',               // LSN de la bitácora
            "state IN ('activa', 'confirmada', 'abortada')",
        ] as $needle) {
            $this->assertTrue(str_contains($sql, $needle), 'el script no contiene ' . $needle);
        }
    }
}
