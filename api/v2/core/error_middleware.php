<?php
/**
 * Traduccion de middlewares/error_middleware.py: convierte excepciones en [status, payload].
 * La capa mas externa: nada por encima puede fallar, asi que todo error sale como JSON uniforme.
 * El detalle tecnico va al log y nunca al cliente.
 * Layer: middlewares (cross-cutting).
 */
final class ErrorMiddleware extends Middleware
{
    public function handle(Request $request, callable $call_next): array
    {
        try {
            return $call_next($request);
        } catch (AppError $error) {
            return [$error->getStatusCode(), error_envelope($error->getMessage())];
        } catch (ValueError | InvalidArgumentException $error) {
            return [400, error_envelope($error->getMessage())];
        } catch (PDOException $error) {
            log_exception("SQL en {$request->method} {$request->path}: " . $error->getMessage());
            [$status, $message] = self::sql_error($error);
            return [$status, error_envelope($message)];
        } catch (Throwable $error) {
            log_exception("Error en {$request->method} {$request->path}: " . $error::class . ': ' . $error->getMessage());
            return [500, error_envelope('Error interno del servidor.')];
        }
    }

    /** Traduce el numero de error nativo de SQL Server a [status, mensaje]. */
    public static function sql_error(PDOException $error): array
    {
        // El servidor no respondio (SQLSTATE 08xxx = conexion, HYT00 = tiempo de espera)
        if (preg_match('/SQLSTATE\[(08|HYT)/', $error->getMessage()) || str_starts_with((string) $error->getCode(), '08')) {
            return [503, 'Error interno de conexión a la base de datos.'];
        }

        $code = isset($error->errorInfo[1]) ? (int) $error->errorInfo[1] : 0;

        // Errores de negocio lanzados con THROW en los procedimientos (50000 en adelante)
        if ($code >= 50000) {
            $text = $error->getMessage();
            $pos = strrpos($text, ']');
            return [409, trim($pos !== false ? substr($text, $pos + 1) : $text)];
        }
        return match ($code) {
            2601, 2627 => [409, 'Ya existe un registro con esos datos (valor duplicado).'],
            547 => [409, 'La operación viola una regla de integridad: una referencia no existe, el registro está en uso o un valor no es válido.'],
            515 => [400, 'Falta un campo obligatorio.'],
            241, 242, 245, 8114, 8115, 8152, 2628 => [400, 'Un campo tiene un formato, tipo o longitud no válidos.'],
            1205 => [503, 'La operación se canceló por un conflicto de concurrencia. Intente de nuevo.'],
            default => [500, 'Error interno de la base de datos.'],
        };
    }
}
