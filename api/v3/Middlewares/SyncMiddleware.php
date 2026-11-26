<?php
declare(strict_types=1);

namespace Apicore\Middlewares;

use Apicore\Core\Request;
use Apicore\Core\Response;
use Apicore\Services\DistributedService;
use Closure;

/**
 * After every successful write, refresh the branch fragments and copy the
 * changes to the Azure SQL mirror.
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
     * Only writes that went well. The manual sync and the manual copy already do
     * that work, and the visit counters do not change the store data.
     */
    public static function shouldSync(string $method, string $path, int $status): bool
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || $status >= 400) {
            return false;
        }
        if (in_array($path, ['/distributed/sync', '/mirror/sync'], true)) {
            return false;
        }
        return !str_starts_with($path, '/analytics/');
    }
}
