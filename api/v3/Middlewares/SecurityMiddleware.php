<?php
declare(strict_types=1);

namespace Apicore\Middlewares;

use Apicore\Core\Request;
use Apicore\Core\Response;
use Apicore\Core\Settings;
use Closure;

/**
 * Transport security and protective response headers.
 *
 * Layer: middlewares (cross-cutting). Sits right inside CorsMiddleware and
 * outside ErrorMiddleware, so every answer gets the headers, errors included.
 *
 *   - REQUIRE_HTTPS=1 refuses plain http with a 403 (the token would travel in
 *     clear text). Behind Caddy, with TRUST_PROXY=1, X-Forwarded-Proto counts.
 *   - nosniff, no-referrer and DENY frames on every response, and no-store so
 *     a browser or proxy never keeps a copy of private data (the catalog sets
 *     its own Cache-Control with its ETag).
 */
final class SecurityMiddleware extends Middleware
{
    public function __construct(private ?bool $requireHttps = null, private ?bool $trustProxy = null)
    {
        $this->requireHttps ??= Settings::instance()->REQUIRE_HTTPS;
        $this->trustProxy ??= Settings::instance()->TRUST_PROXY;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $secure = $this->isSecure($request);
        if ($this->requireHttps && !$secure) {
            $response = Response::error(403, 'Se requiere https.');
        } else {
            $response = $next($request);
        }
        $response->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'Cache-Control' => 'no-store',
        ];
        if ($secure) {
            $response->headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $response;
    }

    /** Direct https, or X-Forwarded-Proto from the proxy when TRUST_PROXY=1. */
    private function isSecure(Request $request): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return $this->trustProxy
            && strtolower(trim($request->headers['x-forwarded-proto'] ?? '')) === 'https';
    }
}
