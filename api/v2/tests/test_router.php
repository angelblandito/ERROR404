<?php
/**
 * Traduccion de tests/test_router.py: coincidencia de rutas, despacho y lectura de la peticion.
 * Sin servidor ni base de datos: un controller de prueba hace de controller real.
 *
 * Diferencia: el despacho de PHP recibe las cabeceras (array) en lugar de token= y user=; el token
 * se pasa como "Authorization: Bearer ..." y el autenticador de prueba lo valida.
 */
class StubController
{
    public static ?StubController $last = null;
    public ?string $auth_token = null;
    public ?array $auth_user = null;
    public array $query = [];
    public string $client_ip = '';
    public bool $closed = false;

    public function __construct()
    {
        self::$last = $this;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

function router_stub_handler(): callable
{
    return static fn(StubController $c, array $params, string $body): Response
        => ok_response(['params' => $params, 'body' => $body]);
}

function router_stub_authenticator(): Router
{
    $authenticator = static function (string $token): array {
        if ($token !== 'raw-token') {
            throw new UnauthorizedError('Invalid token');
        }
        return ['sub' => 1];
    };
    $router = new Router($authenticator);
    $router->add_route('GET', '/items', StubController::class, router_stub_handler(), true);
    $router->add_route('GET', '/items/{id}', StubController::class, router_stub_handler());
    $router->add_route('PUT', '/items/{id}', StubController::class, router_stub_handler());
    return $router;
}

function test_json_object_accepts_object(): void
{
    assert_same(['a' => 1], json_object('{"a": 1}'));
    assert_same([], json_object('{}'));
}

function test_json_object_rejects_non_object(): void
{
    foreach (['[]', '"text"', '3', 'null'] as $body) {
        assert_throws(ValueError::class, static fn() => json_object($body), "body $body");
    }
}

function test_json_object_rejects_invalid_json(): void
{
    assert_throws(ValueError::class, static fn() => json_object('{'));
}

function test_positive_int_absent_is_zero(): void
{
    assert_same(0, positive_int([], 'page'));
    assert_same(0, positive_int(['page' => ''], 'page'));
}

function test_positive_int_valid(): void
{
    assert_same(3, positive_int(['page' => '3'], 'page'));
}

function test_positive_int_invalid_message(): void
{
    foreach (['abc', '1.5', '0', '-1'] as $raw) {
        try {
            positive_int(['page' => $raw], 'page');
            throw new RuntimeException("accepted $raw");
        } catch (ValueError $error) {
            assert_same('page must be a positive integer', $error->getMessage());
        }
    }
}

function test_path_parameter_and_body(): void
{
    $response = router_stub_authenticator()->dispatch('GET', '/items/42', '', ['Authorization' => 'Bearer raw-token']);
    assert_same(200, $response->status);
    assert_same(['id' => '42'], $response->payload['data']['params']);
    assert_same('', $response->payload['data']['body']);
}

function test_unknown_path_is_404(): void
{
    $response = router_stub_authenticator()->dispatch('GET', '/nope', '', []);
    assert_same([404, 'Ruta no encontrada.'], [$response->status, $response->payload['message']]);
}

function test_wrong_method_is_405(): void
{
    $response = router_stub_authenticator()->dispatch('POST', '/items', '', []);
    assert_same([405, 'Método no permitido.'], [$response->status, $response->payload['message']]);
}

function test_trailing_slash_does_not_match(): void
{
    assert_same(404, router_stub_authenticator()->dispatch('GET', '/items/', '', [])->status);
}

function test_allowed_methods(): void
{
    $router = router_stub_authenticator();
    $methods = $router->allowed_methods('/items/1');
    sort($methods);
    assert_same(['GET', 'PUT'], $methods);
    assert_same([], $router->allowed_methods('/nope'));
}

function test_controller_is_closed_after_success(): void
{
    $router = new Router();
    $router->add_route('GET', '/x', StubController::class, router_stub_handler(), true);
    $router->dispatch('GET', '/x', '', []);
    assert_true(StubController::$last !== null && StubController::$last->closed, 'controller was not closed');
}

function test_create_returns_201_and_location(): void
{
    $crud = new class extends BaseController {
        public function create(array $data): int
        {
            return 7;
        }
    };
    $router = new Router();
    $router->add_crud_routes('/items', $crud::class, 'Item', true);
    $response = $router->dispatch('POST', '/items', '{"a": 1}', []);
    assert_same([201, 7], [$response->status, $response->payload['data']]);
    assert_same('/items/7', $response->headers['Location']);
}
