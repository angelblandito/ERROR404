<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use Apicore\Core\Security;

/**
 * Configuración de la cuenta del cliente de la sesión.
 *
 *   PUT  /me/profile    Nombre, apellidos, teléfono y si acepta promociones
 *   POST /me/password   Cambia la contraseña: {current_password, new_password}
 *
 * El correo, la fecha de nacimiento y el tipo de cuenta no se cambian aquí.
 * Al cambiar la contraseña se cierran todas las sesiones de la cuenta.
 *
 * Capa: services. Reglas y SQL, sin HTTP.
 */
final class ProfileService
{
    /**
     * Valida el formulario de datos personales y devuelve las columnas a guardar.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function validate(array $body): array
    {
        $data = [
            'first_name' => StoreService::text($body, 'first_name', 2, 50, 'El nombre'),
            'last_name_paternal' => StoreService::text($body, 'last_name_paternal', 2, 50, 'El apellido paterno'),
            'last_name_maternal' => StoreService::text($body, 'last_name_maternal', 0, 50, 'El apellido materno'),
            'phone' => preg_replace('/\D/', '', StoreService::field($body, 'phone', '')),
            'accepts_marketing' => !empty($body['accepts_marketing']) ? 1 : 0,
        ];
        if (!preg_match('/^\d{10}$/', $data['phone'])) {
            throw new AppError('El teléfono debe tener 10 dígitos.', 400);
        }
        if ($data['last_name_maternal'] === '') {
            $data['last_name_maternal'] = null;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed> perfil actualizado
     */
    public static function update(?array $actor, array $body): array
    {
        $idCustomer = StoreService::requireCustomer($actor);
        $data = self::validate($body);

        $pdo = StoreService::db();
        $stmt = $pdo->prepare('
            UPDATE dbo.customer
            SET first_name = :first_name, last_name_paternal = :last_name_paternal,
                last_name_maternal = :last_name_maternal, phone = :phone,
                accepts_marketing = :accepts_marketing, updated_at = SYSUTCDATETIME()
            WHERE id_customer = :id AND deleted = 0');
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name_paternal' => $data['last_name_paternal'],
            ':last_name_maternal' => $data['last_name_maternal'],
            ':phone' => $data['phone'],
            ':accepts_marketing' => $data['accepts_marketing'],
            ':id' => $idCustomer,
        ]);
        if ($stmt->rowCount() === 0) {
            throw new AppError('La cuenta no existe.', 404);
        }
        return StoreService::profile($pdo, $idCustomer);
    }

    /**
     * Revisa la contraseña actual (400 y no 401, para que la página no cierre la
     * sesión por un error de captura) y guarda la nueva.
     *
     * @param array<string, mixed> $body
     * @return array<string, bool>
     */
    public static function changePassword(?array $actor, array $body): array
    {
        $idCustomer = StoreService::requireCustomer($actor);
        $current = StoreService::field($body, 'current_password', '');
        $new = StoreService::field($body, 'new_password', '');
        if ($current === '') {
            throw new AppError('Escribe tu contraseña actual.', 400);
        }
        if ($new === $current) {
            throw new AppError('La nueva contraseña debe ser distinta de la actual.', 400);
        }
        $hash = StoreService::passwordHash($new);

        $pdo = StoreService::db();
        $rows = StoreService::rows($pdo,
            'SELECT password_hash FROM dbo.customer_credential WHERE id_customer = :id',
            [':id' => $idCustomer]);
        if (!$rows || !Security::verifyPassword($current, (string)$rows[0]['password_hash'])) {
            throw new AppError('La contraseña actual no es correcta.', 400);
        }

        $stmt = $pdo->prepare('
            UPDATE dbo.customer_credential
            SET password_hash = :hash, failed_attempts = 0, locked_until = NULL, updated_at = SYSUTCDATETIME()
            WHERE id_customer = :id');
        $stmt->execute([':hash' => $hash, ':id' => $idCustomer]);

        TokenService::revokeAllOf('customer', $idCustomer);
        return ['changed' => true];
    }
}
