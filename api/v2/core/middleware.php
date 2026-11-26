<?php
/**
 * Traduccion de middlewares/base.py: contrato de middleware y constructor de la cadena.
 *
 * Cada middleware envuelve al siguiente: la cadena se lee de afuera hacia adentro
 * (error -> logging -> seguridad -> endpoint). Todos reciben un Request y devuelven [status, payload].
 * En PHP el Request hace el papel de RequestContext del profe.
 * Layer: middlewares (cross-cutting).
 */
abstract class Middleware
{
    /** Procesa la peticion y delega en call_next. Devuelve [status, payload]. */
    public function handle(Request $request, callable $call_next): array
    {
        return $call_next($request);
    }
}

/** Envuelve el endpoint con los middlewares; el primero de la lista es la capa mas externa. */
function build_pipeline(array $middlewares, callable $endpoint): callable
{
    $chain = static function (int $index) use ($middlewares, $endpoint, &$chain): callable {
        if ($index === count($middlewares)) {
            return $endpoint;
        }
        $middleware = $middlewares[$index];
        $next = $chain($index + 1);
        return static fn(Request $request): array => $middleware->handle($request, $next);
    };
    return $chain(0);
}
