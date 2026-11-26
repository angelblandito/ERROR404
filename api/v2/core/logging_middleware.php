<?php
/**
 * Traduccion de middlewares/logging_middleware.py: una linea por peticion (IP METHOD RUTA STATUS ms).
 * Va dentro de error_middleware, asi registra el status real de un error antes de traducirlo.
 * Layer: middlewares (cross-cutting).
 */
final class LoggingMiddleware extends Middleware
{
    public function handle(Request $request, callable $call_next): array
    {
        $start = microtime(true);
        try {
            [$status, $payload] = $call_next($request);
        } catch (AppError $error) {
            self::log($request, $error->getStatusCode(), $start);
            throw $error;
        } catch (ValueError $error) {
            self::log($request, 400, $start);
            throw $error;
        } catch (Throwable $error) {
            self::log($request, 500, $start);
            throw $error;
        }
        self::log($request, $status, $start);
        return [$status, $payload];
    }

    private static function log(Request $request, int $status, float $start): void
    {
        log_info(sprintf(
            '%s %s %s %d %dms',
            $request->client_ip,
            $request->method,
            $request->path,
            $status,
            (int) round((microtime(true) - $start) * 1000)
        ));
    }
}
