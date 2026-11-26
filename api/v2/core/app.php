<?php
/**
 * Traduccion de main.py del profe: arranque de la API.
 * Orden: validar ajustes -> zona horaria -> pipeline (error, logging, seguridad) -> router -> envio.
 * Layer: core. Lo llama index.php.
 */

/**
 * Atiende la peticion actual.
 * $authenticator: fn(string $token): array; valida el token de sesion y devuelve el actor (lanza 401 si no sirve).
 * $register: fn(Router $router): void; registra las rutas del producto.
 * $after_response: fn(string $method, string $path, int $status): void; tareas que corren despues de
 *   enviar la respuesta completa al navegador (p. ej. refrescar fragmentos y copiar al espejo).
 */
function run_api(callable $authenticator, callable $register, ?callable $after_response = null): void
{
    // Los avisos de PHP van al log del servidor, nunca dentro de la respuesta JSON
    ini_set('display_errors', '0');
    // La respuesta se arma en memoria para poder enviarla completa antes de las tareas posteriores
    ob_start();

    try {
        $settings = Settings::instance();
        $settings->validate();
    } catch (Throwable $error) {
        log_exception('[config] ' . $error->getMessage());
        emit_result(500, error_envelope('Error de configuración del servidor.'), [], false);
        return;
    }
    date_default_timezone_set($settings->APP_TIMEZONE);

    $request = Request::from_globals();
    $endpoint = static function (Request $req) use ($authenticator, $register, $settings): array {
        $router = new Router($authenticator);
        $register($router);

        if ($req->method === 'OPTIONS') {
            $allowed = $router->allowed_methods($req->path);
            if ($allowed === []) {
                return [404, error_envelope('Ruta no encontrada.')];
            }
            $req->extra_headers['Allow'] = implode(', ', array_merge($allowed, ['OPTIONS']));
            return [204, null];
        }

        $response = $router->dispatch(
            $req->method,
            $req->path,
            $req->read_body(),
            $req->headers,
            $req->query,
            $req->client_ip
        );
        $req->extra_headers = array_merge($req->extra_headers, $response->headers);

        $payload = $response->payload;
        if ($settings->APP_DEBUG && is_array($payload)) {
            $payload['debug'] = [
                'method' => $req->method,
                'uri' => $req->path,
                'body' => redact_for_debug($req->debug_body()),
            ];
        }
        return [$response->status, $payload];
    };

    try {
        [$status, $payload] = default_pipeline($endpoint)($request);
        emit_result($status, $payload, $request->extra_headers, $request->is_head);
        if ($after_response !== null) {
            finish_response();
            try {
                $after_response($request->method, $request->path, $status);
            } catch (Throwable $error) {
                log_exception('[despues de responder] ' . $error->getMessage());
            }
        }
    } finally {
        Database::close();
    }
}

/** Envia al navegador lo que hay en el buffer y deja que el script siga trabajando. */
function finish_response(): void
{
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    $output = '';
    while (ob_get_level() > 0) {
        $output = ob_get_clean() . $output;
    }
    if (!headers_sent()) {
        header('Content-Length: ' . strlen($output));
        header('Connection: close');
    }
    echo $output;
    flush();
}

/** Escribe la respuesta. HEAD y 204 no llevan cuerpo. */
function emit_result(int $status, mixed $payload, array $headers, bool $is_head): void
{
    http_response_code($status);
    foreach ($headers as $name => $value) {
        header("$name: $value");
    }
    if ($is_head || $status === 204 || $payload === null) {
        return;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
