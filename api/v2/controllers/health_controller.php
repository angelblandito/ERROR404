<?php
/**
 * Traduccion de controllers/health_controller.py. Probe de salud contra el nodo por defecto.
 * 200 con el estado de la base; publico (sin token).
 * Layer: controllers. Sin modelo: no pasa por BaseController.
 */
class HealthController
{
    public ?string $auth_token = null;
    public ?array $auth_user = null;
    public array $query = [];
    public string $client_ip = '';

    public function get(): Response
    {
        $online = true;
        try {
            Database::connect()->query('SELECT 1')->fetchColumn();
        } catch (Throwable $e) {
            log_exception('[health] ' . $e->getMessage());
            $online = false;
        }
        return ok_response([
            'language' => 'php',
            'architecture' => 'routers, controllers, services, models, schemas',
            'node' => Settings::instance()->JAM_DEFAULT_NODE,
            'database' => $online ? 'en_linea' : 'sin_conexion',
            'pdo_sqlsrv' => extension_loaded('pdo_sqlsrv'),
        ], 'API online');
    }

    public function close(): void
    {
    }
}
