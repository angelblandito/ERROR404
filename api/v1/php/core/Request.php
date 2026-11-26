<?php
declare(strict_types=1);

namespace Apicore\Core;

use Closure;

/**
 * Everything one request carries while it crosses the pipeline.
 *
 * Layer: core. Request and Response only carry data; neither touches the
 * socket, so middlewares and tests can build and inspect them freely. Only
 * Core\Application reads the request and writes the response back to the client.
 */
final class Request
{
    /** 1 MB cap for request bodies, the same limit as the Python server. */
    public const MAX_BODY_BYTES = 1024 * 1024;

    public ?string $token = null;  // raw bearer token, set by AuthMiddleware
    public ?array $user = null;    // decoded token payload, set by AuthMiddleware

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers lowercase header names
     * @param Closure(): string     $readBody reads the body on demand, so a
     *                                        middleware can reject a request
     *                                        (401, 413) before it is read
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $headers,
        public string $clientIp,
        private Closure $readBody,
    ) {
    }

    public function body(): string
    {
        return ($this->readBody)();
    }

    /**
     * Build the request from the PHP superglobals.
     *
     * The body is passed as a closure, so a middleware can reject a request
     * (401, 413) before it is read.
     */
    public static function fromGlobals(): self
    {
        $headers = self::headersFromGlobals();
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::routePath((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            $_GET,
            $headers,
            self::clientIpFrom((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), $headers),
            static fn (): string => self::readBodyFromGlobals(),
        );
    }

    /**
     * Path the router sees. Under Apache (XAMPP or the virtual machine) the API
     * lives in .../api/v3/, with "php -S" at the root: both end up as /health.
     */
    public static function routePath(string $uri): string
    {
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $path = (string) preg_replace('#^.*?/api/v3(?=/|$)#', '', $path);
        return rtrim($path, '/') ?: '/';
    }

    /**
     * Client IP. Behind a proxy on the same machine (Caddy on the virtual machine)
     * every request comes from 127.0.0.1; with TRUST_PROXY=1 the last address of
     * X-Forwarded-For is used, the one the proxy added (the earlier ones can be
     * made up by the client).
     *
     * @param array<string, string> $headers
     */
    public static function clientIpFrom(string $remote, array $headers): string
    {
        $loopback = in_array($remote, ['127.0.0.1', '::1'], true);
        if (!$loopback || !Settings::instance()->TRUST_PROXY || empty($headers['x-forwarded-for'])) {
            return $remote;
        }
        $parts = array_map('trim', explode(',', $headers['x-forwarded-for']));
        $last = (string) end($parts);
        return filter_var($last, FILTER_VALIDATE_IP) !== false ? $last : $remote;
    }

    /** Request headers with lowercase names (the pipeline never guesses the case). */
    private static function headersFromGlobals(): array
    {
        $collected = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $collected[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $collected['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $collected['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }
        // Apache with mod_rewrite leaves the Authorization header in REDIRECT_HTTP_AUTHORIZATION
        if (!isset($collected['authorization']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $collected['authorization'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        return $collected;
    }

    /** Read the body, rejecting an invalid or oversized Content-Length. */
    private static function readBodyFromGlobals(): string
    {
        $raw = (string) ($_SERVER['CONTENT_LENGTH'] ?? '');
        if ($raw === '') {
            $length = 0;
        } elseif (preg_match('/^-?\d+$/', $raw) !== 1) {
            throw new AppError('Invalid Content-Length header', 400);
        } else {
            $length = (int) $raw;
        }
        if ($length < 0) {
            throw new AppError('Invalid Content-Length header', 400);
        }
        if ($length > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeError('Request body too large');
        }
        $body = (string) file_get_contents('php://input');
        if ($body !== '' && !mb_check_encoding($body, 'UTF-8')) {
            throw new AppError('Request body must be UTF-8', 400);
        }
        return $body;
    }

    /** HEAD reuses the GET route: same handler and headers, body suppressed by Core\Application. */
    public static function routeMethod(string $method): string
    {
        return $method === 'HEAD' ? 'GET' : $method;
    }
}
