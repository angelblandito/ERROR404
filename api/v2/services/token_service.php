<?php
/**
 * Traduccion de services/token_service.py: sesiones de cliente.
 *
 * El token es aleatorio (Security::new_token); en dbo.customer_session solo se guarda su SHA-256.
 * El router llama a decode_token() con el token de la cabecera Authorization y pone el actor en
 * el controller: los controllers nunca leen la cabecera.
 * Layer: services. Sin HTTP: lanza UnauthorizedError.
 */
final class TokenService
{
    /**
     * Actor de la sesion: cliente y su tipo de cuenta.
     * Lanza UnauthorizedError si el token no tiene formato, no existe, se cerro o ya vencio.
     */
    public static function decode_token(string $token): array
    {
        if (!Security::token_format_ok($token)) {
            throw new UnauthorizedError('La sesión no es válida o ya venció. Inicia sesión de nuevo.');
        }
        $stmt = Database::connect()->prepare('
            SELECT s.id_customer, t.code AS tier, t.can_manage_store, t.support_minutes
            FROM dbo.customer_session AS s
            JOIN dbo.customer AS c ON c.id_customer = s.id_customer AND c.deleted = 0
            JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
            WHERE s.token_hash = :token_hash
              AND s.revoked = 0
              AND s.expires_at > SYSUTCDATETIME()');
        $stmt->execute([':token_hash' => Security::hash_token(strtolower($token))]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new UnauthorizedError('La sesión no es válida o ya venció. Inicia sesión de nuevo.');
        }
        $id = (int) $row['id_customer'];
        return [
            'actor_type' => 'customer',
            'sub' => $id,
            'role' => $row['tier'],
            'id_customer' => $id,
            'tier' => $row['tier'],
            'can_manage' => (bool) $row['can_manage_store'],
            'support_minutes' => (int) $row['support_minutes'],
        ];
    }

    /** Crea una sesion para el cliente y devuelve el token (solo esta vez sale en claro). */
    public static function create_session(PDO $pdo, int $id_customer): string
    {
        $token = Security::new_token();
        $stmt = $pdo->prepare('
            INSERT INTO dbo.customer_session (token_hash, id_customer, expires_at)
            VALUES (:token_hash, :id_customer, DATEADD(DAY, :days, SYSUTCDATETIME()))');
        $stmt->bindValue(':token_hash', Security::hash_token($token), PDO::PARAM_STR);
        $stmt->bindValue(':id_customer', $id_customer, PDO::PARAM_INT);
        $stmt->bindValue(':days', Settings::instance()->SESSION_DAYS, PDO::PARAM_INT);
        $stmt->execute();
        return $token;
    }

    /** Cierra la sesion del token (logout). */
    public static function revoke(PDO $pdo, string $token): void
    {
        if (!Security::token_format_ok($token)) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE dbo.customer_session SET revoked = 1 WHERE token_hash = :token_hash');
        $stmt->execute([':token_hash' => Security::hash_token(strtolower($token))]);
    }
}
