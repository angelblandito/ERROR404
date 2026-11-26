<?php
declare(strict_types=1);

namespace Apicore\Tests;

use Apicore\Core\AppError;
use Apicore\Core\Router;
use Apicore\Middlewares\RateLimitMiddleware;
use Apicore\Routers\RouterRegistry;
use Apicore\Services\AccountService;
use Apicore\Services\MailService;
use Apicore\Services\ProfileService;
use Apicore\Services\RecoveryService;
use Apicore\Services\RegistrationService;

/**
 * Registro con confirmación por correo, regla de Prioritario, correo real por SMTP
 * y acceso a las rutas nuevas.
 */
final class NuevasFuncionesTest extends TestCase
{
    /* ---------------- Regla de Prioritario ---------------- */

    public function testPrioritarioNecesitaMasDe50MilYMasDeUnAnio(): void
    {
        $ok = AccountService::priorityProgress('standard', 53500.0, 425, '2025-09-27');
        $this->assertTrue($ok['eligible']);
        $this->assertTrue($ok['meets_spent']);
        $this->assertTrue($ok['meets_days']);
        $this->assertSame(0.0, $ok['missing_spent']);
        $this->assertSame(0, $ok['missing_days']);
        $this->assertSame(100, $ok['spent_percent']);
    }

    public function testExactamente50MilOUnAnioNoAlcanza(): void
    {
        // "más de": $50,000.00 justos y 365 días justos todavía no cumplen
        $edge = AccountService::priorityProgress('standard', 50000.0, 365, '2025-11-26');
        $this->assertFalse($edge['meets_spent']);
        $this->assertFalse($edge['meets_days']);
        $this->assertFalse($edge['eligible']);
        $this->assertSame(0.01, $edge['missing_spent']);
        $this->assertSame(1, $edge['missing_days']);

        $justo = AccountService::priorityProgress('standard', 50000.01, 366, '2025-11-25');
        $this->assertTrue($justo['eligible']);
    }

    public function testCuentaNuevaConPocasCompras(): void
    {
        $nuevo = AccountService::priorityProgress('standard', 5350.0, 120, '2026-07-29');
        $this->assertFalse($nuevo['eligible']);
        $this->assertSame(44650.01, $nuevo['missing_spent']);
        $this->assertSame(246, $nuevo['missing_days']);
        $this->assertSame(10, $nuevo['spent_percent']);
        $this->assertSame(32, $nuevo['days_percent']);
        $this->assertSame('2027-07-30', $nuevo['days_ready_on']);
    }

    public function testSoloComprasOSoloAntiguedadNoAlcanza(): void
    {
        $this->assertFalse(AccountService::priorityProgress('standard', 90000.0, 30, '2026-10-27')['eligible']);
        $this->assertFalse(AccountService::priorityProgress('standard', 1000.0, 900, '2024-06-09')['eligible']);
    }

    public function testPrioritarioYAdministradorNoSonElegibles(): void
    {
        // Ya tienen los beneficios: el botón de ascenso no aparece
        $this->assertFalse(AccountService::priorityProgress('priority', 61750.0, 730, '2024-11-26')['eligible']);
        $this->assertFalse(AccountService::priorityProgress('admin', 99999.0, 2000, '2021-01-01')['eligible']);
    }

    public function testValoresNegativosNoRompenElAvance(): void
    {
        $raro = AccountService::priorityProgress('standard', -10.0, -3, '2026-11-26');
        $this->assertSame(0.0, $raro['spent']);
        $this->assertSame(0, $raro['days']);
        $this->assertSame(0, $raro['spent_percent']);
    }

    /* ---------------- Registro con confirmación ---------------- */

    public function testElTokenDelEnlaceDebeSerHexDe64(): void
    {
        $token = str_repeat('ab', 32);
        $this->assertSame($token, RegistrationService::checkToken(' ' . strtoupper($token) . ' '));
        foreach (['', 'abc', str_repeat('z', 64), str_repeat('a', 63), "' OR 1=1 --"] as $bad) {
            $error = $this->assertThrows(AppError::class, static fn () => RegistrationService::checkToken($bad));
            $this->assertSame(400, $error->statusCode());
        }
    }

    public function testElRegistroValidaAntesDeGuardarNada(): void
    {
        $valido = [
            'first_name' => 'Ana', 'last_name_paternal' => 'Pérez', 'email' => 'ana@correo.com',
            'phone' => '664 123 4567', 'birth_date' => '2000-01-01', 'sex' => 'F',
            'password' => 'Segura2026', 'accepts_terms' => true,
        ];
        $casos = [
            'sin nombre' => ['first_name' => ''],
            'correo malo' => ['email' => 'ana@'],
            'teléfono corto' => ['phone' => '12345'],
            'menor de 15' => ['birth_date' => date('Y-m-d', strtotime('-11 years'))],
            'contraseña débil' => ['password' => 'solotexto'],
            'sin aceptar términos' => ['accepts_terms' => false],
        ];
        foreach ($casos as $caso => $cambio) {
            $error = $this->assertThrows(AppError::class, static fn () => RegistrationService::start(array_merge($valido, $cambio)), $caso);
            $this->assertSame(400, $error->statusCode(), $caso);
        }
    }

    public function testElTipoDeCuentaNuncaSaleDelFormulario(): void
    {
        // Aunque alguien mande id_account_tier o tier, el registro guarda solo los campos del formulario
        $data = \Apicore\Services\StoreService::validateRegistration([
            'first_name' => 'Ana', 'last_name_paternal' => 'Pérez', 'email' => 'ANA@Correo.com',
            'phone' => '6641234567', 'birth_date' => '2000-01-01', 'sex' => 'f',
            'password' => 'Segura2026', 'accepts_terms' => true,
            'id_account_tier' => 3, 'tier' => 'admin', 'role' => 'admin',
        ]);
        $this->assertFalse(array_key_exists('id_account_tier', $data));
        $this->assertFalse(array_key_exists('tier', $data));
        $this->assertFalse(array_key_exists('role', $data));
        $this->assertFalse(array_key_exists('password', $data));
        $this->assertSame('ana@correo.com', $data['email']);
        $this->assertTrue(password_verify('Segura2026', $data['password_hash']));
    }

    public function testElEnlaceDeConfirmacionApuntaALaTienda(): void
    {
        $token = str_repeat('c', 64);
        $link = RecoveryService::pageLink(['return_url' => 'http://localhost/ERROR404/index.html'], '#/confirmar?token=' . $token);
        $this->assertSame('http://localhost/ERROR404/index.html#/confirmar?token=' . $token, $link);

        // Una dirección ajena en return_url no se usa (el correo no puede llevar a otro sitio)
        $other = RecoveryService::pageLink(['return_url' => 'https://sitio-falso.com/robar'], '#/confirmar?token=' . $token);
        $this->assertFalse(str_contains($other, 'sitio-falso'));
        $this->assertTrue(str_ends_with($other, '#/confirmar?token=' . $token));
    }

    /* ---------------- Rutas y seguridad ---------------- */

    private function router(): Router
    {
        $router = new Router();
        RouterRegistry::registerAll($router);
        return $router;
    }

    public function testRutasPublicasYProtegidas(): void
    {
        $router = $this->router();
        // Registro y confirmación: públicas (aún no hay sesión)
        $this->assertSame(true, $router->routePublic('POST', '/auth/register'));
        $this->assertSame(true, $router->routePublic('POST', '/auth/confirm'));
        // Avance y ascenso a Prioritario: solo con sesión
        $this->assertSame(false, $router->routePublic('GET', '/me/priority'));
        $this->assertSame(false, $router->routePublic('POST', '/me/priority/upgrade'));
        // Tickets (también los urgentes): solo con sesión
        $this->assertSame(false, $router->routePublic('POST', '/support/tickets'));
        // No hay forma de pedir el ascenso con GET
        $this->assertNull($router->routePublic('GET', '/me/priority/upgrade'));
    }

    public function testElAscensoSinSesionEs401(): void
    {
        $router = $this->router();
        foreach ([['GET', '/me/priority'], ['POST', '/me/priority/upgrade']] as [$method, $path]) {
            $error = $this->assertThrows(AppError::class, static fn () => $router->dispatch($method, $path, '{}'));
            $this->assertSame(401, $error->statusCode(), $method . ' ' . $path);
        }
    }

    /* ---------------- Correo real (SMTP) ---------------- */

    public function testMensajeMimeConTextoYHtml(): void
    {
        $raw = MailService::message('tienda@jam.mx', 'JAM Sport', 'cliente@correo.com', 'Confirma tu correo ñ',
            MailService::html('Confirma tu correo', 'Hola <Ana>', 'http://localhost/#/confirmar?token=x'), 'Hola Ana');
        $this->assertTrue(str_contains($raw, 'Subject: =?UTF-8?B?' . base64_encode('Confirma tu correo ñ') . '?='));
        $this->assertTrue(str_contains($raw, 'To: <cliente@correo.com>'));
        $this->assertTrue(str_contains($raw, 'multipart/alternative'));
        $html = MailService::html('Asunto', 'Hola <script>', 'http://x/#/a?b=1&c=2');
        $this->assertFalse(str_contains($html, '<script>'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;'));
        $this->assertTrue(str_contains($html, 'http://x/#/a?b=1&amp;c=2'));
    }

    public function testSinServidorDeCorreoNoSeEnviaNiFalla(): void
    {
        // Sin MAIL_HOST la tienda sigue funcionando: el correo solo queda en la bandeja
        putenv('MAIL_HOST');
        $this->assertFalse(MailService::enabled());
        $this->assertFalse(MailService::sendSafely('a@b.com', 'x', 'y', null));
    }

    /* ---------------- Límites de peticiones ---------------- */

    public function testLasRutasNuevasTienenLimite(): void
    {
        $this->assertSame('confirmar', RateLimitMiddleware::ruleFor('POST', '/auth/confirm')[4]);
        $this->assertSame('ascenso', RateLimitMiddleware::ruleFor('POST', '/me/priority/upgrade')[4]);
        $this->assertSame(300, RateLimitMiddleware::GENERAL_LIMIT);
    }

    public function testElLimiteGeneralNoAgrupaAlProxyLocal(): void
    {
        // Sin TRUST_PROXY=1 detrás de Caddy todos llegan como 127.0.0.1: no se les cuenta juntos
        foreach (['127.0.0.1', '127.0.0.5', '::1', ''] as $ip) {
            $this->assertTrue(RateLimitMiddleware::isLoopback($ip), $ip);
        }
        foreach (['189.203.10.4', '10.0.0.4', '2806:2f0::1'] as $ip) {
            $this->assertFalse(RateLimitMiddleware::isLoopback($ip), $ip);
        }
    }

    /* ---------------- Configuración de la cuenta ---------------- */

    public function testElPerfilValidaNombreYTelefono(): void
    {
        $ok = ProfileService::validate([
            'first_name' => ' Ana ', 'last_name_paternal' => 'López', 'last_name_maternal' => '',
            'phone' => '(664) 123-4567', 'accepts_marketing' => true,
        ]);
        $this->assertSame('Ana', $ok['first_name']);
        $this->assertSame('6641234567', $ok['phone']);
        $this->assertNull($ok['last_name_maternal']);
        $this->assertSame(1, $ok['accepts_marketing']);

        $bad = $this->assertThrows(AppError::class, static fn () => ProfileService::validate([
            'first_name' => 'Ana', 'last_name_paternal' => 'López', 'phone' => '123',
        ]));
        $this->assertSame(400, $bad->statusCode());
        $short = $this->assertThrows(AppError::class, static fn () => ProfileService::validate([
            'first_name' => 'A', 'last_name_paternal' => 'López', 'phone' => '6641234567',
        ]));
        $this->assertSame(400, $short->statusCode());
    }

    public function testElPerfilNoCambiaCorreoNiTipoDeCuenta(): void
    {
        $data = ProfileService::validate([
            'first_name' => 'Ana', 'last_name_paternal' => 'López', 'phone' => '6641234567',
            'email' => 'otro@correo.com', 'id_account_tier' => 3, 'tier' => 'admin',
        ]);
        $this->assertSame(['first_name', 'last_name_paternal', 'last_name_maternal', 'phone', 'accepts_marketing'], array_keys($data));
    }

    public function testConfiguracionSoloConSesion(): void
    {
        $router = $this->router();
        $this->assertSame(false, $router->routePublic('PUT', '/me/profile'));
        $this->assertSame(false, $router->routePublic('POST', '/me/password'));
        foreach ([['PUT', '/me/profile'], ['POST', '/me/password']] as [$method, $path]) {
            $error = $this->assertThrows(AppError::class, static fn () => $router->dispatch($method, $path, '{}'));
            $this->assertSame(401, $error->statusCode(), $method . ' ' . $path);
        }
        $this->assertSame('contrasena', RateLimitMiddleware::ruleFor('POST', '/me/password')[4]);
        $this->assertSame('perfil', RateLimitMiddleware::ruleFor('PUT', '/me/profile')[4]);
    }
}
