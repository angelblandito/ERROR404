<?php
/**
 * Traduccion de core/router.py: tabla de rutas, CRUD generico, autenticacion del token y despacho.
 *
 * Como en el profe, el router hace la autenticacion (decode_token inyectado) y los controllers
 * nunca leen la cabecera Authorization. Un controller nuevo por peticion; se cierra al final.
 * Layer: core (cross-cutting). Nunca contiene SQL ni logica de negocio.
 */
const MAX_PAGE_SIZE = 100;

/** Cuerpo JSON que debe ser un objeto. Igual que json_object() del profe. */
function json_object(string $body): array
{
    if (trim($body) === '') {
        return [];
    }
    // Primero como objeto: asi "[]" (lista) se distingue de "{}" (objeto vacio)
    $object = json_decode($body, false);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ValueError('El cuerpo de la petición no es un JSON válido.');
    }
    if (!is_object($object)) {
        throw new ValueError('El cuerpo de la petición debe ser un objeto JSON.');
    }
    return json_decode($body, true);
}

/** Parametro de consulta entero positivo; 0 si no viene. Mensaje generico (igual que el profe). */
function positive_int(array $query, string $name): int
{
    $raw = $query[$name] ?? null;
    if ($raw === null || $raw === '') {
        return 0;
    }
    if (!is_string($raw) && !is_int($raw) || !preg_match('/^-?\d+$/', (string) $raw)) {
        throw new ValueError("$name must be a positive integer");
    }
    $value = (int) $raw;
    if ($value < 1) {
        throw new ValueError("$name must be a positive integer");
    }
    return $value;
}

final class Router
{
    /** @var array<int, array{string, string, callable, string, bool}> */
    private array $routes = [];

    /** @var callable(string): array  Valida el token y devuelve el actor; lanza 401 si no es valido. */
    private $authenticator;

    public function __construct(?callable $authenticator = null)
    {
        $this->authenticator = $authenticator ?? static fn(string $token): array => throw new UnauthorizedError('La sesión no es válida o ya venció. Inicia sesión de nuevo.');
    }

    /**
     * Registra una ruta. pattern: '/moods/{id}'; cada {nombre} es un parametro.
     * handler: fn(BaseController $c, array $params, string $body): Response
     */
    public function add_route(string $method, string $pattern, string $controller_class, callable $handler, bool $public = false): void
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, $controller_class, $public];
    }

    /** Las 6 rutas CRUD de una entidad: GET lista, GET uno, POST, PUT (completo), PATCH (parcial), DELETE. */
    public function add_crud_routes(string $path, string $controller_class, string $label, bool $public = false, array $public_methods = []): void
    {
        $item = "$path/{id}";
        $not_found = static fn(): Response => Response::error(404, 'Registro no encontrado.');

        $list_all = static function (BaseController $c, array $p, string $body): Response {
            $page = positive_int($c->query, 'page');
            $size = positive_int($c->query, 'size');
            if ($size > MAX_PAGE_SIZE) {
                throw new ValueError('size must be ' . MAX_PAGE_SIZE . ' or less');
            }
            return ok_response($c->get_all($page ?: null, $size ?: null), 'Consulta realizada');
        };
        $get_one = static function (BaseController $c, array $p, string $body) use ($not_found): Response {
            $element = $c->get_by_id((int) $p['id']);
            return $element !== null ? ok_response($element, 'Consulta realizada') : $not_found();
        };
        $create = static function (BaseController $c, array $p, string $body) use ($path): Response {
            $record_id = $c->create(json_object($body));
            return new Response(201, ok_envelope($record_id, 'Registro creado'), ['Location' => "$path/$record_id"]);
        };
        $update_with = static function (string $action) use ($not_found): callable {
            return static function (BaseController $c, array $p, string $body) use ($action, $not_found): Response {
                $result = $c->$action((int) $p['id'], json_object($body));
                return match ($result) {
                    BaseModel::NOT_FOUND => $not_found(),
                    BaseModel::UNCHANGED => ok_response(null, 'No hay cambios que aplicar.'),
                    BaseModel::UPDATED => ok_response(null, 'Registro actualizado'),
                    default => throw new ValueError('Unexpected update result: ' . $result),
                };
            };
        };
        $delete = static function (BaseController $c, array $p, string $body) use ($not_found): Response {
            return $c->delete((int) $p['id']) ? ok_response(null, 'Registro eliminado') : $not_found();
        };

        $get_public = $public || in_array('GET', $public_methods, true);
        $this->add_route('GET', $path, $controller_class, $list_all, $get_public);
        $this->add_route('GET', $item, $controller_class, $get_one, $get_public);
        $this->add_route('POST', $path, $controller_class, $create, $public || in_array('POST', $public_methods, true));
        $this->add_route('PUT', $item, $controller_class, $update_with('update'), $public || in_array('PUT', $public_methods, true));
        $this->add_route('PATCH', $item, $controller_class, $update_with('patch'), $public || in_array('PATCH', $public_methods, true));
        $this->add_route('DELETE', $item, $controller_class, $delete, $public || in_array('DELETE', $public_methods, true));
    }

    /** Rutas cuyo patron coincide con el camino, con los parametros numericos validados. */
    private function matching(string $path): array
    {
        $found = [];
        foreach ($this->routes as $route) {
            if (preg_match($route[1], $path, $m) && self::numeric_params_ok($m)) {
                $found[] = [$route, $m];
            }
        }
        return $found;
    }

    /** Parametros id e id_* deben ser digitos; si no, la ruta no existe (404). */
    private static function numeric_params_ok(array $match): bool
    {
        foreach ($match as $name => $value) {
            if (is_string($name) && ($name === 'id' || str_starts_with($name, 'id_')) && !ctype_digit($value)) {
                return false;
            }
        }
        return true;
    }

    /** true = publica, false = protegida, null = el metodo no tiene esa ruta. */
    public function route_public(string $method, string $path): ?bool
    {
        foreach ($this->matching($path) as [$route]) {
            if ($route[0] === $method) {
                return $route[4];
            }
        }
        return null;
    }

    /** Metodos registrados para el camino (para la cabecera Allow de OPTIONS). */
    public function allowed_methods(string $path): array
    {
        $methods = [];
        foreach ($this->matching($path) as [$route]) {
            $methods[] = $route[0];
        }
        return array_values(array_unique($methods));
    }

    /**
     * Ejecuta la ruta. 404 si ningun patron coincide; 405 si el camino existe con otro metodo.
     * Autentica las rutas protegidas antes del handler (401 si el token falta o no es valido).
     */
    public function dispatch(string $method, string $path, string $body, array $headers, array $query = [], string $client_ip = ''): Response
    {
        $path_found = false;
        foreach ($this->matching($path) as [$route, $match]) {
            $path_found = true;
            [$route_method, , $handler, $controller_class, $public] = $route;
            if ($route_method !== $method) {
                continue;
            }

            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $token = self::bearer_token($headers);
            $user = null;
            if (!$public) {
                if ($token === null) {
                    throw new UnauthorizedError('Inicia sesión para continuar.');
                }
                $user = ($this->authenticator)($token);
            } elseif ($token !== null) {
                try {
                    $user = ($this->authenticator)($token);
                } catch (UnauthorizedError) {
                    $user = null; // ruta publica: un token invalido se ignora
                }
            }

            $controller = new $controller_class();
            $controller->auth_token = $token;
            $controller->auth_user = $user;
            $controller->query = $query;
            $controller->client_ip = $client_ip;
            try {
                return $handler($controller, $params, $body);
            } finally {
                $controller->close();
            }
        }
        if ($path_found) {
            return Response::error(405, 'Método no permitido.');
        }
        return Response::error(404, 'Ruta no encontrada.');
    }

    /** Token de "Authorization: Bearer <token>". El esquema no distingue mayusculas (RFC 7235). */
    public static function bearer_token(array $headers): ?string
    {
        $value = null;
        foreach ($headers as $name => $header) {
            if (strtolower((string) $name) === 'authorization') {
                $value = (string) $header;
                break;
            }
        }
        if ($value === null) {
            return null;
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($value), $m)) {
            throw new UnauthorizedError('La sesión no es válida o ya venció. Inicia sesión de nuevo.');
        }
        return $m[1];
    }
}
