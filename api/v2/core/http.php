<?php
/**
 * Traduccion de core/http.py: objetos Request y Response. Solo llevan datos; no tocan el socket.
 * El formato de respuesta es el del profesor de PHP: {success, data, message}.
 * Layer: core.
 */
final class Request
{
    public const MAX_BODY_BYTES = 1048576; // 1 MB

    public string $method;
    public bool $is_head;
    public string $path;
    public array $query;
    /** Cabeceras con nombre en minusculas. */
    public array $headers;
    public string $client_ip;
    public string $body = '';
    /** Cabeceras de respuesta que agrega un middleware o el endpoint. */
    public array $extra_headers = [];

    private ?string $raw = null;

    public function __construct(string $method, string $path, array $query, array $headers, string $client_ip)
    {
        $this->is_head = $method === 'HEAD';
        $this->method = $this->is_head ? 'GET' : $method; // HEAD usa la ruta de GET, sin cuerpo
        $this->path = $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->client_ip = $client_ip;
    }

    /** Lee la peticion actual de PHP ($_SERVER, $_GET, cabeceras). */
    public static function from_globals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        // Apache con mod_rewrite deja la cabecera Authorization en REDIRECT_HTTP_AUTHORIZATION
        if (!isset($headers['authorization'])) {
            $authorization = (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
            if ($authorization === '' && function_exists('apache_request_headers')) {
                foreach (apache_request_headers() as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $authorization = (string) $value;
                    }
                }
            }
            if ($authorization !== '') {
                $headers['authorization'] = $authorization;
            }
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        // En Apache la API vive en .../api/v2/; con "php -S" en la raiz
        $path = (string) preg_replace('#^.*?/api/v2(?=/|$)#', '', $path);
        $path = rtrim($path, '/') ?: '/';
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }

    /** Lee el cuerpo bajo demanda: rechaza mas de 1 MB sin leerlo entero (igual que read_body del profe). */
    public function read_body(): string
    {
        if ($this->raw !== null) {
            return $this->raw;
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeError('El cuerpo de la petición es demasiado grande.');
        }
        $this->raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (strlen($this->raw) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeError('El cuerpo de la petición es demasiado grande.');
        }
        if ($this->raw !== '' && !mb_check_encoding($this->raw, 'UTF-8')) {
            throw new ValueError('El cuerpo de la petición debe estar en UTF-8.');
        }
        return $this->raw;
    }

    /** Cuerpo tal como llego, decodificado si es JSON valido (solo para el bloque debug). */
    public function debug_body(): mixed
    {
        $raw = $this->raw ?? '';
        if (trim($raw) === '') {
            return null;
        }
        return json_decode($raw, true);
    }
}

/** Respuesta de un endpoint o middleware: codigo, cuerpo (ya en formato success/data/message) y cabeceras. */
final class Response
{
    public function __construct(
        public int $status = 200,
        public mixed $payload = null,
        public array $headers = []
    ) {
    }

    public static function error(int $status, string $message): self
    {
        return new self($status, error_envelope($message));
    }
}

/** Respuesta correcta: {"success": true, "data": ..., "message": "..."}. */
function ok_envelope(mixed $data, string $message = 'Todo bien'): array
{
    return ['success' => true, 'data' => $data, 'message' => $message];
}

/** Respuesta de error: {"success": false, "data": null, "message": "..."}. */
function error_envelope(string $message): array
{
    return ['success' => false, 'data' => null, 'message' => $message];
}

function ok_response(mixed $data, string $message = 'Todo bien', int $status = 200): Response
{
    return new Response($status, ok_envelope($data, $message));
}
