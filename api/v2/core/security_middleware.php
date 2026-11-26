<?php
/**
 * Seguridad de transporte y cabeceras: HTTPS obligatorio (REQUIRE_HTTPS) y CORS solo para
 * CORS_ALLOW_ORIGIN. Agrega las cabeceras de respuesta en $request->extra_headers.
 * Layer: middlewares (cross-cutting). Va dentro de logging, asi tambien registra los 403.
 */
final class SecurityMiddleware extends Middleware
{
    public function handle(Request $request, callable $call_next): array
    {
        $settings = Settings::instance();
        $secure = self::is_secure($request, $settings);
        $request->extra_headers = array_merge($request->extra_headers, self::headers($request, $settings, $secure));

        if ($settings->REQUIRE_HTTPS && !$secure) {
            return [403, error_envelope('Se requiere https.')];
        }
        return $call_next($request);
    }

    /** HTTPS directo, o X-Forwarded-Proto del proxy si TRUST_PROXY=1. */
    public static function is_secure(Request $request, Settings $settings): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return $settings->TRUST_PROXY
            && strtolower(trim($request->headers['x-forwarded-proto'] ?? '')) === 'https';
    }

    private static function headers(Request $request, Settings $settings, bool $secure): array
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
            'Vary' => 'Origin',
        ];
        $origin = $request->headers['origin'] ?? '';
        if ($origin !== '' && in_array($origin, $settings->cors_origins(), true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Admin-Key';
            $headers['Access-Control-Max-Age'] = '600';
        }
        if ($secure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $headers;
    }
}
