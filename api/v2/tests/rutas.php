<?php
/**
 * Prueba de rutas sin base de datos: cada ruta que usa la pagina llega a su controller y a la
 * funcion de servicio correcta, con la autenticacion y el codigo HTTP esperados.
 * Los servicios se reemplazan por funciones simuladas que solo registran la llamada.
 *
 * Uso (desde api/v2):   php tests/rutas.php   (codigo de salida 0 = todo bien)
 */
foreach (['core/settings.php', 'core/exceptions.php', 'core/http.php', 'utils/date_utils.php', 'core/logger.php',
    'core/database.php', 'core/security.php', 'core/router.php', 'controllers/api_controller.php'] as $file) {
    require __DIR__ . '/../' . $file;
}
define('JAM_DEFAULT_NODE', 'central');
$CALLS = [];

function rec(string $fn, array $args) { global $CALLS; $CALLS[] = [$fn, $args]; return ['fn' => $fn]; }
class FakePdo extends PDO { public function __construct() {} }
function store_db(): PDO { return new FakePdo(); }
function store_require_customer(?array $a): int { if ($a === null) throw new HttpError(401, 'Inicia sesión para continuar.'); return (int)$a['id_customer']; }
function store_require_admin(?array $a): array { if ($a === null) throw new HttpError(401, 'x'); if (empty($a['can_manage'])) throw new HttpError(403, 'Esta sección es solo para administradores.'); return $a; }
function market_require_member(?array $a): array { if ($a === null) throw new HttpError(401,'x'); return $a; }
function dist_error_text(Throwable $e): string { return 'causa: ' . $e->getMessage(); }
function checkout_create(?array $a, array $b): array { if ($a === null) throw new HttpError(401, 'Inicia sesión para completar la compra.'); return rec('checkout_create', [$a, $b]); }
foreach (['store_catalog','store_reviews','store_also_bought','store_create_review','account_announcements','store_postal_code','season_theme_current',
 'store_register','store_login','store_logout','store_profile','recovery_forgot','recovery_reset','store_my_orders','account_my_tickets','account_create_ticket',
 'market_compare','analytics_ping','analytics_leave','analytics_search','analytics_install','analytics_report','analytics_online',
 'admin_summary','admin_customers','admin_set_tier','admin_evaluate_priority','admin_orders','admin_set_order_status','admin_set_price','admin_set_stock',
 'admin_tickets','admin_answer_ticket','admin_announcements','admin_create_announcement','admin_delete_announcement','admin_emails',
 'market_competitors','market_offers','market_create','market_update','market_delete',
 'dist_nodes_status','dist_inventory','dist_orders','dist_customers','dist_fragments_summary','dist_replication_status','dist_sync','mirror_copy'] as $f) {
    eval("function $f(...\$a): array { return rec('$f', \$a); }");
}
foreach (['shop','auth','me','checkout','market','analytics','admin','distributed'] as $n) {
    require __DIR__ . "/../controllers/{$n}_controller.php";
}
$auth = static function (string $t): array {
    $tokens = ['a' => 'admin', 'c' => 'standard'];
    $t = substr($t, 0, 1);
    if (!isset($tokens[$t])) throw new UnauthorizedError('La sesión no es válida');
    return ['actor_type'=>'customer','sub'=>9,'id_customer'=>9,'tier'=>$tokens[$t],'can_manage'=>$t==='a','support_minutes'=>15];
};
$router = new Router($auth);
foreach (['shop','auth','me','checkout','market','analytics','admin','distributed'] as $n) {
    require_once __DIR__ . "/../routers/{$n}_router.php";
    $cls = ucfirst($n) . 'Router'; $cls::register_routes($router);
}
$cases = [
 // metodo, ruta, token, cuerpo, query, status esperado, funcion esperada
 ['GET','/store/catalog',null,'',[],200,'store_catalog'],
 ['GET','/store/products/12/reviews',null,'',[],200,'store_reviews'],
 ['GET','/store/products/12/also-bought',null,'',[],200,'store_also_bought'],
 ['POST','/store/reviews',null,'{"id_product":1}',[],401,null],
 ['POST','/store/reviews','c','{"id_product":1}',[],201,'store_create_review'],
 ['GET','/store/announcements',null,'',[],200,'account_announcements'],
 ['GET','/store/announcements','zz','',[],200,'account_announcements'],
 ['GET','/season_theme/current',null,'',['date'=>'2026-12-24'],200,'season_theme_current'],
 ['GET','/postal-codes/22000',null,'',[],200,'store_postal_code'],
 ['GET','/postal-codes/2200x',null,'',[],404,null],
 ['POST','/auth/register',null,'{"email":"a@b.c"}',[],201,'store_register'],
 ['POST','/auth/login',null,'{"email":"a@b.c"}',[],200,'store_login'],
 ['POST','/auth/login',null,'[1,2]',[],400,null],
 ['POST','/auth/logout','c','',[],200,'store_logout'],
 ['GET','/auth/me',null,'',[],401,null],
 ['GET','/auth/me','c','',[],200,'store_profile'],
 ['POST','/auth/forgot',null,'{"email":"a@b.c"}',[],200,'recovery_forgot'],
 ['POST','/auth/reset',null,'{"token":"x"}',[],200,'recovery_reset'],
 ['GET','/me/orders','c','',[],200,'store_my_orders'],
 ['GET','/support/tickets','c','',[],200,'account_my_tickets'],
 ['POST','/support/tickets','c','{"subject":"x"}',[],201,'account_create_ticket'],
 ['POST','/orders/checkout',null,'{}',[],401,null],
 ['POST','/orders/checkout','c','{"items":[]}',[],201,'checkout_create'],
 ['GET','/market','c','',[],200,'market_compare'],
 ['POST','/analytics/ping',null,'{"visitor":"v"}',[],200,'analytics_ping'],
 ['POST','/analytics/leave',null,'{}',[],200,'analytics_leave'],
 ['POST','/analytics/search',null,'{}',[],201,'analytics_search'],
 ['POST','/analytics/install',null,'{}',[],201,'analytics_install'],
 ['GET','/admin/analytics','c','',[],403,null],
 ['GET','/admin/analytics','a','',[],200,'analytics_report'],
 ['GET','/admin/analytics/online','a','',[],200,'analytics_online'],
 ['GET','/admin/summary','c','',[],403,null],
 ['GET','/admin/summary','a','',[],200,'admin_summary'],
 ['GET','/admin/customers','a','',[],200,'admin_customers'],
 ['PUT','/admin/customers/5/tier','a','{"tier":"priority"}',[],200,'admin_set_tier'],
 ['POST','/admin/customers/evaluate','a','',[],200,'admin_evaluate_priority'],
 ['GET','/admin/orders','a','',['status'=>'paid'],200,'admin_orders'],
 ['PUT','/admin/orders/8/status','a','{"status":"paid"}',[],200,'admin_set_order_status'],
 ['PUT','/admin/products/3','a','{"base_price":10}',[],200,'admin_set_price'],
 ['PUT','/admin/inventory','a','{"id_inventory":1}',[],200,'admin_set_stock'],
 ['GET','/admin/tickets','a','',[],200,'admin_tickets'],
 ['PUT','/admin/tickets/4','a','{"response":"x"}',[],200,'admin_answer_ticket'],
 ['GET','/admin/announcements','a','',[],200,'admin_announcements'],
 ['POST','/admin/announcements','a','{"title":"x"}',[],201,'admin_create_announcement'],
 ['DELETE','/admin/announcements/4','a','',[],200,'admin_delete_announcement'],
 ['GET','/admin/emails','a','',[],200,'admin_emails'],
 ['GET','/admin/market','a','',[],200,'market_offers'],
 ['POST','/admin/market','a','{"x":1}',[],201,'market_create'],
 ['PUT','/admin/market/2','a','{"x":1}',[],200,'market_update'],
 ['DELETE','/admin/market/2','a','',[],200,'market_delete'],
 ['GET','/nodes','c','',[],403,null],
 ['GET','/nodes','a','',[],200,'dist_nodes_status'],
 ['GET','/distributed/inventory','a','',['node'=>'tijuana'],200,'dist_inventory'],
 ['GET','/distributed/orders','a','',[],200,'dist_orders'],
 ['GET','/distributed/customers','a','',[],200,'dist_customers'],
 ['GET','/distributed/fragments','a','',[],200,'dist_fragments_summary'],
 ['GET','/replication/status','a','',[],200,'dist_replication_status'],
 ['POST','/distributed/sync','a','',[],200,'dist_sync'],
 ['POST','/mirror/sync','a','',['full'=>'1'],200,'mirror_copy'],
 ['GET','/admin/nada','a','',[],404,null],
 ['DELETE','/store/catalog',null,'',[],405,null],
];
$fail = 0;
foreach ($cases as [$m,$p,$tok,$body,$q,$want,$fn]) {
    $CALLS = [];
    $headers = $tok ? ['authorization' => "Bearer $tok"] : [];
    try { $r = $router->dispatch($m, $p, $body, $headers, $q); $status = $r->status; $payload = $r->payload; }
    catch (Throwable $e) { $status = status_of($e); $payload = ['message' => $e->getMessage()]; }
    $called = $CALLS ? $CALLS[count($CALLS)-1][0] : null;
    $ok = $status === $want && ($fn === null ? true : in_array($fn, array_column($CALLS, 0), true));
    if (!$ok) { $fail++; echo "FALLA $m $p => $status (" . ($payload['message'] ?? '') . ") llamo " . json_encode(array_column($CALLS,0)) . "\n"; }
}
// Argumentos que el controller debe pasar al servicio
function args_of(Router $router, string $m, string $p, string $body, string $tok, array $q): array
{
    global $CALLS;
    $CALLS = [];
    $router->dispatch($m, $p, $body, ['authorization' => "Bearer $tok"], $q);
    return $CALLS[0][1] ?? [];
}
$checks = [
    ['filtro por nodo', args_of($router, 'GET', '/distributed/inventory', '', 'a', ['node' => 'cdmx']) === ['cdmx']],
    ['copia completa al espejo', args_of($router, 'POST', '/mirror/sync', '', 'a', ['full' => '1']) === [true]],
    ['id y cuerpo del pedido', array_slice(args_of($router, 'PUT', '/admin/orders/8/status', '{"status":"paid"}', 'a', []), 2) === [8, ['status' => 'paid']]],
    ['token para cerrar sesion', args_of($router, 'POST', '/auth/logout', '', 'c123', []) === ['c123']],
    ['cliente en la visita', (args_of($router, 'POST', '/analytics/ping', '{"v":1}', 'c1', [])[2]['id_customer'] ?? null) === 9],
];
foreach ($checks as [$name, $ok]) {
    if (!$ok) {
        $fail++;
        echo "FALLA $name\n";
    }
}
$r = $router->dispatch('GET', '/store/catalog', '', [], []);
if ($r->payload !== ['success' => true, 'data' => ['fn' => 'store_catalog'], 'message' => 'Catálogo']) {
    $fail++;
    echo "FALLA formato {success, data, message}\n";
}
$total = count($cases) + count($checks) + 1;
echo ($total - $fail) . " pasan, $fail fallan\n";
exit($fail === 0 ? 0 : 1);
