<?php
/*
 * Recuperación de contraseña.
 *
 *   POST /auth/forgot  {email, return_url}  Registra el correo con el enlace para restablecer
 *   POST /auth/reset   {token, password}    Cambia la contraseña y cierra todas las sesiones
 *
 * /auth/forgot responde igual exista o no el correo, para no revelar qué
 * cuentas están registradas.
 *
 * Layer: services. Logica de negocio y SQL; sin HTTP. Las rutas viven en routers y los
 * controllers de controllers llaman a estas funciones con el actor de la sesion.
 */

const RECOVERY_MINUTES = 30;
const RECOVERY_COOLDOWN_SECONDS = 60;

/* El enlace solo puede apuntar al mismo servidor que atiende la petición. */
function recovery_link(array $body, string $token): string
{
    $fragment = '#/restablecer?token=' . $token;
    $returnUrl = (string)($body['return_url'] ?? '');
    $parts = parse_url($returnUrl);
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));

    if ($parts && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && isset($parts['host'])) {
        $host = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        if ($host === $requestHost) {
            return $parts['scheme'] . '://' . $host . ($parts['path'] ?? '/') . $fragment;
        }
    }
    return notify_store_link($fragment);
}

function recovery_forgot(array $body): array
{
    $email = strtolower(trim((string)($body['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpError(400, 'Escribe un correo válido.');
    }

    $pdo = store_db();
    $rows = store_rows($pdo, '
        SELECT c.id_customer, c.first_name,
               (SELECT MAX(pr.created_at) FROM dbo.password_reset AS pr
                WHERE pr.id_customer = c.id_customer
                  AND pr.created_at > DATEADD(SECOND, -CAST(:cooldown AS INT), SYSUTCDATETIME())) AS recent
        FROM dbo.customer AS c
        JOIN dbo.customer_credential AS cr ON cr.id_customer = c.id_customer
        WHERE c.email = :email AND c.deleted = 0',
        [':email' => $email, ':cooldown' => RECOVERY_COOLDOWN_SECONDS]);

    if ($rows && $rows[0]['recent'] === null) {
        $idCustomer = (int)$rows[0]['id_customer'];
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('
            INSERT INTO dbo.password_reset (token_hash, id_customer, expires_at)
            VALUES (:token_hash, :id_customer, DATEADD(MINUTE, :minutes, SYSUTCDATETIME()))');
        $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->bindValue(':id_customer', $idCustomer, PDO::PARAM_INT);
        $stmt->bindValue(':minutes', RECOVERY_MINUTES, PDO::PARAM_INT);
        $stmt->execute();

        notify_password_reset($pdo, $idCustomer, $rows[0]['first_name'], recovery_link($body, $token), RECOVERY_MINUTES);
    }

    return ['sent' => true, 'minutes' => RECOVERY_MINUTES];
}

function recovery_reset(array $body): array
{
    $token = strtolower(trim((string)($body['token'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new HttpError(400, 'El enlace no es válido. Solicita uno nuevo.');
    }
    $hash = store_password_hash((string)($body['password'] ?? ''));

    $pdo = store_db();
    $pdo->beginTransaction();
    try {
        $rows = store_rows($pdo, '
            SELECT id_customer
            FROM dbo.password_reset WITH (UPDLOCK, ROWLOCK)
            WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > SYSUTCDATETIME()',
            [':token_hash' => hash('sha256', $token)]);
        if (!$rows) {
            throw new HttpError(410, 'El enlace venció o ya se usó. Solicita uno nuevo.');
        }
        $idCustomer = (int)$rows[0]['id_customer'];

        $stmt = $pdo->prepare('
            UPDATE dbo.customer_credential
            SET password_hash = :hash, failed_attempts = 0, locked_until = NULL, updated_at = SYSUTCDATETIME()
            WHERE id_customer = :id');
        $stmt->execute([':hash' => $hash, ':id' => $idCustomer]);

        $stmt = $pdo->prepare('
            UPDATE dbo.password_reset SET used_at = SYSUTCDATETIME()
            WHERE id_customer = :id AND used_at IS NULL');
        $stmt->execute([':id' => $idCustomer]);

        $stmt = $pdo->prepare('UPDATE dbo.customer_session SET revoked = 1 WHERE id_customer = :id AND revoked = 0');
        $stmt->execute([':id' => $idCustomer]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['reset' => true];
}

