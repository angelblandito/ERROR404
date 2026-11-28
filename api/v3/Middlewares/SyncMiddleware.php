<?php
declare(strict_types=1);

namespace Apicore\Middlewares;

use Apicore\Core\Request;
use Apicore\Core\Response;
use Apicore\Services\DistributedService;
use Closure;

/**
 * After every successful write, refresh the branch fragments right away and copy
 * the changes to the Azure SQL mirror once the answer has reached the client.
 *
 * Layer: middlewares (cross-cutting). Sits inside ErrorMiddleware: a request
 * that failed never reaches the sync, and a sync that fails is logged by the
 * service without changing the answer the client already earned.
 */
final class SyncMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (self::shouldSync($request->method, $request->path, $response->status)) {
            DistributedService::afterWrite();
        }
        return $response;
    }

    /**
     * Routes whose writes never touch the fragmented or mirrored tables: sessions,
     * login attempts, pending signups and passwords. The manual sync and the manual
     * copy already do that work themselves, and a checkpoint changes no store data.
     */
    private const SKIP = [
        '/distributed/sync', '/mirror/sync', '/distributed/checkpoint',
        '/auth/login', '/auth/logout', '/auth/register', '/auth/forgot', '/auth/reset', '/me/password',
    ];

    /** Only writes that went well and that change store data (visit counters do not). */
    public static function shouldSync(string $method, string $path, int $status): bool
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || $status >= 400) {
            return false;
        }
        if (in_array($path, self::SKIP, true)) {
            return false;
        }
        return !str_starts_with($path, '/analytics/');
    }
}
