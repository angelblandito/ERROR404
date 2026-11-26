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
 * Request budget per IP on the routes that write without a session or that can
 * be abused (sign up, password recovery, analytics, coupons, reviews, tickets
 * and checkout).
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
        ['POST', '#^/auth/login$#', 30, 900, 'login'],
        ['POST', '#^/auth/forgot$#', 5, 900, 'recuperar'],
        ['POST', '#^/auth/reset$#', 10, 900, 'restablecer'],
        ['POST', '#^/analytics/(ping|leave|search|install)$#', 120, 60, 'estadisticas'],
        ['GET', '#^/store/coupons/[^/]+$#', 20, 300, 'cupones'],
        ['POST', '#^/store/reviews$#', 10, 600, 'resenas'],
        ['POST', '#^/support/tickets$#', 10, 600, 'tickets'],
        ['POST', '#^/orders/checkout$#', 20, 600, 'compras'],
    ];

    public function __construct(private ?bool $enabled = null)
    {
        $this->enabled ??= Settings::instance()->RATE_LIMIT;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $rule = $this->enabled ? self::ruleFor(Request::routeMethod($request->method), $request->path) : null;
        if ($rule !== null) {
            [, , $limit, $seconds, $name] = $rule;
            $limiter = new RateLimiter($limit, $seconds);
            $key = 'ip:' . $name . ':' . $request->clientIp;
            try {
                $limiter->check($key);
            } catch (TooManyRequestsError) {
                return new Response(
                    429,
                    ['error' => 'Demasiadas solicitudes desde tu conexión. Intenta de nuevo más tarde.'],
                    ['Retry-After' => (string) $seconds],
                );
            }
            $limiter->fail($key);
        }
        return $next($request);
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
