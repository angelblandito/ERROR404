<?php
/**
 * Reglas de acceso del CRUD generico de JAM: que puede hacer cada actor en cada tabla.
 *
 * Actor = cliente con sesion (ver TokenService::decode_token): actor_type 'customer', sub (id),
 * role = tipo de cuenta (standard | priority | admin) y can_manage (account_tier.can_manage_store).
 *
 *   - Sin sesion: solo lectura de las tablas que la tienda muestra a todos (promociones, estados
 *     y temporadas).
 *   - Administrador (can_manage): todas las acciones en todas las tablas del CRUD.
 *   - Estandar y Prioritario: solo la lectura publica. Sus datos los ven por las rutas propias de
 *     la tienda (/auth/me, /me/orders, /support/tickets), que ya filtran por su id.
 *
 * Los controllers generados llaman a estos metodos; las reglas viven aqui, en un solo lugar.
 * Layer: services. Sin HTTP: lanza ForbiddenError / ValueError.
 */
final class Policy
{
    private const ACTIONS = ['create', 'read', 'update', 'patch', 'delete'];

    /** Tablas que cualquiera puede leer, con o sin sesion. */
    public const PUBLIC_READ = ['promotion', 'state', 'season_theme'];

    /** Tablas con contrasena propia (se guarda con bcrypt, nunca en texto). */
    private const WITH_PASSWORD = ['owner', 'employee'];

    public static function allowed(?array $actor, string $table, string $action): bool
    {
        if (!in_array($action, self::ACTIONS, true)) {
            return false;
        }
        if (self::is_admin($actor)) {
            return true;
        }
        return $action === 'read' && in_array($table, self::PUBLIC_READ, true);
    }

    public static function is_admin(?array $actor): bool
    {
        return $actor !== null && !empty($actor['can_manage']);
    }

    /** Filtro de filas para el actor (null = sin filtro). El CRUD solo lo usa el administrador. */
    public static function scope(?array $actor, string $table): ?array
    {
        return null;
    }

    /** Fija los campos que decide el servidor (no el cliente). */
    public static function prepare(?array $actor, string $table, array &$data, string $action): void
    {
        unset($data['source_node'], $data['updated_at'], $data['deleted'], $data['password_hash']);
    }

    /** Reglas que dependen de los datos enviados. Lanza ForbiddenError si el actor no puede. */
    public static function authorize(?array $actor, string $table, string $action, array $data): void
    {
        if (!self::is_admin($actor)) {
            throw new ForbiddenError('Esta operación es solo para administradores.');
        }
    }

    /** Convierte el campo "password" en password_hash (bcrypt). Solo en tablas con contrasena. */
    public static function hash_password(array &$data): void
    {
        if (!array_key_exists('password', $data)) {
            return;
        }
        $min = Settings::instance()->PASSWORD_MIN_LENGTH;
        if (!is_string($data['password']) || strlen($data['password']) < $min) {
            throw new ValueError("La contraseña debe tener al menos $min caracteres.");
        }
        $data['password_hash'] = Security::hash_password($data['password']);
        unset($data['password']);
    }

    public static function has_password(string $table): bool
    {
        return in_array($table, self::WITH_PASSWORD, true);
    }
}
