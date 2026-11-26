<?php
declare(strict_types=1);

namespace Apicore\Tests;

use Apicore\Controllers\JamController;
use Apicore\Core\AppError;
use Apicore\Core\ForbiddenError;
use Apicore\Core\Request;
use Apicore\Core\Response;
use Apicore\Core\Settings;
use Apicore\Core\Router;
use Apicore\Core\UnauthorizedError;
use Apicore\Middlewares\RateLimitMiddleware;
use Apicore\Middlewares\SecurityMiddleware;
use Apicore\Services\StoreService;
use PDOException;
use Apicore\Middlewares\SyncMiddleware;
use ValueError;

/** Store access rules, routes and responses. */
final class JamTest extends TestCase
{
    public function testFixedRouteWinsOverParameterRoute(): void
    {
        $router = new Router();
        $router->addRoute('GET', '/season_theme/{id}', JamRouteDouble::class, static fn () => new Response(200, ['crud' => true]), true);
        $router->addRoute('GET', '/season_theme/current', JamRouteDouble::class, static fn () => new Response(200, ['current' => true]), true);
        $this->assertSame(['current' => true], $router->dispatch('GET', '/season_theme/current', '')->payload);
        $this->assertSame(['crud' => true], $router->dispatch('GET', '/season_theme/3', '')->payload);
    }

    public function testOkAndCachedResponses(): void
    {
        $ok = Response::ok(['a' => 1], 201, ['X-Total-Count' => '9']);
        $this->assertSame(201, $ok->status);
        $this->assertSame(['status' => 'ok', 'data' => ['a' => 1]], $ok->payload);
        $this->assertSame('9', $ok->headers['X-Total-Count']);

        $first = Response::cached(['p' => [1, 2]], []);
        $this->assertSame(200, $first->status);
        $again = Response::cached(['p' => [1, 2]], ['if-none-match' => $first->headers['ETag']]);
        $this->assertSame(304, $again->status);
        $this->assertNull($again->payload);
        $changed = Response::cached(['p' => [1, 2, 3]], ['if-none-match' => $first->headers['ETag']]);
        $this->assertSame(200, $changed->status);
    }

    public function testRoutePathUnderApacheAndPhpServer(): void
    {
        $this->assertSame('/health', Request::routePath('/ERROR404/api/v3/health'));
        $this->assertSame('/store/catalog', Request::routePath('/api/v3/store/catalog/?x=1'));
        $this->assertSame('/health', Request::routePath('/health'));
        $this->assertSame('/', Request::routePath('/ERROR404/api/v3'));
    }

    public function testSyncOnlyAfterSuccessfulWrites(): void
    {
        $this->assertTrue(SyncMiddleware::shouldSync('POST', '/product', 201));
        $this->assertTrue(SyncMiddleware::shouldSync('PUT', '/admin/inventory', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('GET', '/product', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/product', 400));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/distributed/sync', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/analytics/ping', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/auth/login', 200));
        $this->assertFalse(SyncMiddleware::shouldSync('POST', '/auth/register', 202));
        $this->assertTrue(SyncMiddleware::shouldSync('POST', '/auth/confirm', 201));
    }

    public function testRateLimitAnswers429AfterTheBudget(): void
    {
        $this->assertSame('registro', RateLimitMiddleware::ruleFor('POST', '/auth/register')[4]);
        $this->assertNull(RateLimitMiddleware::ruleFor('GET', '/store/catalog'));

        $middleware = new RateLimitMiddleware(true);
        $ip = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255);
        $request = new Request('POST', '/auth/register', [], [], $ip, static fn (): string => '');
        $statuses = [];
        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $middleware->handle($request, static fn () => new Response(201, []))->status;
        }
        $this->assertSame([201, 201, 201, 201, 201, 429], $statuses);
        $this->assertSame(201, (new RateLimitMiddleware(false))->handle($request, static fn () => new Response(201, []))->status);
    }

    public function testTablesAreForAdministradorOnly(): void
    {
        foreach ([null, ['role' => 'standard'], ['role' => 'priority'], ['role' => 'Admin']] as $user) {
            $controller = new JamControllerDouble();
            $controller->authUser = $user;
            $expected = $user === null ? UnauthorizedError::class : ForbiddenError::class;
            $this->assertThrows($expected, static fn () => $controller->getAll());
            $this->assertThrows($expected, static fn () => $controller->authorize(1, 'delete'));
        }
        $controller = new JamControllerDouble();
        $controller->authUser = ['role' => 'admin'];
        $controller->authorize(1, 'update');
        $this->assertTrue(true, 'admin may write');
    }

    public function testSqlServerErrorsBecomeReadableAnswers(): void
    {
        $sqlError = static function (int $number, string $text, string $state = '42000'): PDOException {
            $error = new PDOException("SQLSTATE[$state]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]$text");
            $error->errorInfo = [$state, $number, "[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]$text"];
            return $error;
        };
        $stock = AppError::fromDatabase($sqlError(50010, 'No hay existencias suficientes.'));
        $this->assertSame(409, $stock->statusCode());
        $this->assertSame('No hay existencias suficientes.', $stock->getMessage());
        $this->assertSame(409, AppError::fromDatabase($sqlError(2627, 'Violation of UNIQUE KEY'))->statusCode());
        $this->assertSame(409, AppError::fromDatabase($sqlError(547, 'FOREIGN KEY'))->statusCode());
        $this->assertSame(400, AppError::fromDatabase($sqlError(8152, 'String would be truncated'))->statusCode());
        $this->assertSame(503, AppError::fromDatabase($sqlError(0, 'Login timeout', '08001'))->statusCode());
        $hidden = AppError::fromDatabase($sqlError(208, "Invalid object name 'dbo.secret'"));
        $this->assertSame(500, $hidden->statusCode());
        $this->assertSame('Internal server error', $hidden->getMessage());
        $this->assertSame(409, AppError::statusOf($sqlError(50009, 'El cupón no existe.')));
    }

    public function testBodyFieldsKeepTheirType(): void
    {
        $this->assertSame('abc', StoreService::field(['a' => 'abc'], 'a'));
        $this->assertSame('5', StoreService::field(['a' => 5], 'a'));
        $this->assertSame('x', StoreService::field([], 'a', 'x'));
        $this->assertThrows(AppError::class, static fn () => StoreService::field(['a' => ['b']], 'a'));
        $this->assertSame(7, StoreService::number(['n' => 7], 'n'));
        $this->assertSame(7.5, StoreService::number(['n' => '7.5'], 'n'));
        $this->assertSame(-1, StoreService::number([], 'n', -1));
        $this->assertThrows(AppError::class, static fn () => StoreService::number(['n' => 'abc'], 'n'));
        $this->assertThrows(AppError::class, static fn () => StoreService::number(['n' => [1]], 'n'));
        $this->assertThrows(AppError::class, static fn () => StoreService::number(['n' => true], 'n'));
    }

    public function testShortJwtSecretStopsTheServer(): void
    {
        $folder = sys_get_temp_dir() . '/jam_settings_' . bin2hex(random_bytes(4));
        mkdir($folder);
        try {
            file_put_contents($folder . '/.env', "JWT_SECRET=corta\n");
            $this->assertThrows(\ValueError::class, static fn () => (new Settings($folder))->validate());
            file_put_contents($folder . '/.env', 'JWT_SECRET=' . str_repeat('a', 64) . "\n");
            (new Settings($folder))->validate();
            $this->assertTrue(true, 'a 64 character key is accepted');
        } finally {
            @unlink($folder . '/.env');
            @rmdir($folder);
        }
    }

    public function testReadOnlyTableRefusesEvenTheAdministrador(): void
    {
        $controller = new ReadOnlyDouble();
        $controller->authUser = ['role' => 'admin'];
        $this->assertThrows(ForbiddenError::class, static fn () => $controller->create(['code' => 'x']));
        $this->assertThrows(ForbiddenError::class, static fn () => $controller->authorize(1, 'update'));
        $this->assertThrows(ForbiddenError::class, static fn () => $controller->authorize(1, 'delete'));
    }

    public function testSecurityHeadersAndHttps(): void
    {
        $request = new Request('GET', '/x', [], [], '127.0.0.1', static fn (): string => '');
        $response = (new SecurityMiddleware(false, false))->handle($request, static fn () => new Response(200, []));
        $this->assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        $this->assertSame('no-store', $response->headers['Cache-Control']);
        $this->assertSame('DENY', $response->headers['X-Frame-Options']);

        $kept = (new SecurityMiddleware(false, false))->handle($request, static fn () => new Response(200, [], ['Cache-Control' => 'no-cache']));
        $this->assertSame('no-cache', $kept->headers['Cache-Control']);

        $refused = (new SecurityMiddleware(true, false))->handle($request, static fn () => new Response(200, []));
        $this->assertSame(403, $refused->status);

        $proxied = new Request('GET', '/x', [], ['x-forwarded-proto' => 'https'], '127.0.0.1', static fn (): string => '');
        $this->assertSame(200, (new SecurityMiddleware(true, true))->handle($proxied, static fn () => new Response(200, []))->status);
    }

    public function testPasswordIsHashedAndNeverAcceptedAsHash(): void
    {
        $controller = new JamControllerDouble();
        $prepared = $controller->prepare(['first_name' => 'Ana', 'password' => 'Clave2026x']);
        $this->assertFalse(isset($prepared['password']));
        $this->assertSame('$2y$', substr($prepared['password_hash'], 0, 4));

        $this->assertThrows(ValueError::class, static fn () => $controller->prepare(['password_hash' => '$2y$x']));
        $this->assertThrows(ValueError::class, static fn () => $controller->prepare(['first_name' => 'Ana']));
        $this->assertThrows(ValueError::class, static fn () => $controller->prepare(['password' => 'corta']));
        // On update the password may be omitted: it stays unchanged
        $this->assertSame(['first_name' => 'Ana'], $controller->prepare(['first_name' => 'Ana'], 5));
    }
}

/** Test double: a table controller with a password and no database. */
final class JamControllerDouble extends JamController
{
    protected const MODEL_CLASS = null;
    protected const HAS_PASSWORD = true;
}

/** Test double: a read-only table, like account_tier. */
final class ReadOnlyDouble extends JamController
{
    protected const MODEL_CLASS = null;
    protected const READ_ONLY = true;
}

/** Test double: the router only needs a class it can instantiate and close. */
final class JamRouteDouble
{
    public ?string $authToken = null;
    public ?array $authUser = null;
    public ?array $query = null;
    public string $clientIp = '';
    public array $headers = [];

    public function close(): void
    {
    }
}
