<?php
declare(strict_types=1);

namespace Apicore\Middlewares;

use Apicore\Core\RateLimiter;
use Apicore\Core\Request;
use Apicore\Core\Response;
use Apicore\Core\Settings;
use Apicore\Core\TooManyRequestsError;
use Closure;

/**
 * Request budget per IP: a general limit on every route, and a stricter one
 * on the routes that can be abused (sign up and its confirmation, password
 * recovery, analytics, coupons, reviews, tickets, checkout and the upgrade
 * to Prioritario).
 *
 * Layer: middlewares (cross-cutting). Sits inside ErrorMiddleware, so the 429
 * keeps the CORS headers. RATE_LIMIT=0 turns it off (load tests).
 *
 * It reuses Core\RateLimiter: every request counts as one "attempt" of the
 * caller's IP for that rule.
 */
final class RateLimitMiddleware extends Middleware
{
    /** [method, path pattern, requests, seconds, rule name] */
    public const RULES = [
        ['POST', '#^/auth/register$#', 5, 3600, 'registro'],
        ['POST', '#^/auth/confirm$#', 20, 3600, 'confirmar'],
        ['POST', '#^/auth/login$#', 30, 900, 'login'],
        ['POST', '#^/auth/forgot$#', 5, 900, 'recuperar'],
        ['POST', '#^/auth/reset$#', 10, 900, 'restablecer'],
        ['POST', '#^/analytics/(ping|leave|search|install)$#', 120, 60, 'estadisticas'],
        ['GET', '#^/store/coupons/[^/]+$#', 20, 300, 'cupones'],
        ['POST', '#^/store/reviews$#', 10, 600, 'resenas'],
        ['POST', '#^/support/tickets$#', 10, 600, 'tickets'],
        ['POST', '#^/orders/checkout$#', 20, 600, 'compras'],
        ['POST', '#^/me/priority/upgrade$#', 10, 600, 'ascenso'],
    ];

    /** Requests per IP on any route inside GENERAL_SECONDS: stops floods from one connection. */
    public const GENERAL_LIMIT = 300;

    public const GENERAL_SECONDS = 60;

    public function __construct(private ?bool $enabled = null)
    {
        $this->enabled ??= Settings::instance()->RATE_LIMIT;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->enabled) {
            return $next($request);
        }
        // Loopback = a proxy without TRUST_PROXY=1: every client would share one budget
        if (!self::isLoopback($request->clientIp)
            && !self::spend('general', $request->clientIp, self::GENERAL_LIMIT, self::GENERAL_SECONDS)) {
            return self::tooMany(self::GENERAL_SECONDS);
        }
        $rule = self::ruleFor(Request::routeMethod($request->method), $request->path);
        if ($rule !== null) {
            [, , $limit, $seconds, $name] = $rule;
            if (!self::spend($name, $request->clientIp, $limit, $seconds)) {
                return self::tooMany($seconds);
            }
        }
        return $next($request);
    }

    /** Count one request of the IP for the rule; false when its budget is used up. */
    private static function spend(string $name, string $ip, int $limit, int $seconds): bool
    {
        $limiter = new RateLimiter($limit, $seconds);
        $key = 'ip:' . $name . ':' . $ip;
        try {
            $limiter->check($key);
        } catch (TooManyRequestsError) {
            return false;
        }
        $limiter->fail($key);
        return true;
    }

    private static function tooMany(int $seconds): Response
    {
        return new Response(
            429,
            ['error' => 'Demasiadas solicitudes desde tu conexión. Intenta de nuevo más tarde.'],
            ['Retry-After' => (string) $seconds],
        );
    }

    public static function isLoopback(string $ip): bool
    {
        return $ip === '' || $ip === '::1' || str_starts_with($ip, '127.');
    }

    /** @return array{0: string, 1: string, 2: int, 3: int, 4: string}|null */
    public static function ruleFor(string $method, string $path): ?array
    {
        foreach (self::RULES as $rule) {
            if ($rule[0] === $method && preg_match($rule[1], $path) === 1) {
                return $rule;
            }
        }
        return null;
    }
}
