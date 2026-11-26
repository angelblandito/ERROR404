<?php
/**
 * Traduccion de middlewares/__init__.py: la cadena por defecto del servidor.
 * error -> logging -> seguridad -> endpoint.
 * Layer: middlewares (cross-cutting).
 */
function default_pipeline(callable $endpoint): callable
{
    return build_pipeline([
        new ErrorMiddleware(),
        new LoggingMiddleware(),
        new SecurityMiddleware(),
    ], $endpoint);
}

/** Oculta valores de claves sensibles en el bloque debug (a cualquier nivel). */
function redact_for_debug(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $clean = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && preg_match('/pass|token|secret|hash|key/i', $key)) {
            $clean[$key] = '***';
        } else {
            $clean[$key] = redact_for_debug($item);
        }
    }
    return $clean;
}
