<?php
declare(strict_types=1);

namespace Apicore\Core;

use PDOException;
use RuntimeException;
use Throwable;
use ValueError;

/**
 * Application errors that carry their own HTTP status code.
 *
 * Layer: core (cross-cutting). Raised by any layer, translated to JSON by
 * Middlewares\ErrorMiddleware. The whole hierarchy lives in this one file, the
 * counterpart of core/exceptions.py, so the status codes are read together.
 *
 * Core\Autoloader maps these class names to this file: PSR-4 assumes one class
 * per file, and grouping them is worth the one explicit map entry each.
 */

/** Base error: any layer may raise it with an explicit status code. */
class AppError extends RuntimeException
{
    public function __construct(string $message, private int $statusCode = 500)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * HTTP status an exception maps to: AppError keeps its own, ValueError is a
     * 400, a SQL Server error the one of fromDatabase(), the rest a 500.
     */
    public static function statusOf(Throwable $error): int
    {
        if ($error instanceof self) {
            return $error->statusCode();
        }
        if ($error instanceof ValueError) {
            return 400;
        }
        if ($error instanceof PDOException) {
            return self::fromDatabase($error)->statusCode();
        }
        return 500;
    }

    /**
     * Translate a SQL Server error into an AppError the client can read.
     *
     * The store procedures stop an operation with THROW 50000+ and a message
     * meant for the customer (no stock, expired coupon, inactive branch...):
     * that message is returned as a 409. The other native numbers become a
     * generic text; the technical detail only goes to the log.
     */
    public static function fromDatabase(PDOException $error): self
    {
        // The server did not answer (SQLSTATE 08xxx = connection, HYT00 = timeout)
        if (preg_match('/SQLSTATE\[(08|HYT)/', $error->getMessage()) === 1 || str_starts_with((string) $error->getCode(), '08')) {
            return new ServiceUnavailableError('Database unavailable');
        }
        $number = (int) ($error->errorInfo[1] ?? 0);
        if ($number >= 50000) {
            $text = (string) ($error->errorInfo[2] ?? $error->getMessage());
            $position = strrpos($text, ']');
            return new self(trim($position !== false ? substr($text, $position + 1) : $text), 409);
        }
        return match ($number) {
            2601, 2627 => new self('Ya existe un registro con esos datos.', 409),
            547 => new self('Una referencia no existe, el registro está en uso o un valor no es válido.', 409),
            515 => new self('Falta un campo obligatorio.', 400),
            241, 242, 245, 8114, 8115, 8152, 2628 => new self('Un campo tiene un formato, tipo o longitud no válidos.', 400),
            1205 => new ServiceUnavailableError('La operación chocó con otra al mismo tiempo. Intenta de nuevo.'),
            default => new self('Internal server error', 500),
        };
    }
}

/** 401: the request has no valid bearer token. */
final class UnauthorizedError extends AppError
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}

/** 403: the caller is authenticated but may not touch this record. */
final class ForbiddenError extends AppError
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}

/** 413: the request body exceeds the 1 MB cap. */
final class PayloadTooLargeError extends AppError
{
    public function __construct(string $message = 'Payload too large')
    {
        parent::__construct($message, 413);
    }
}

/** 429: the caller exceeded its request budget. */
final class TooManyRequestsError extends AppError
{
    public function __construct(string $message = 'Too many requests')
    {
        parent::__construct($message, 429);
    }
}

/** 503: the database is unreachable or the server is saturated. */
final class ServiceUnavailableError extends AppError
{
    public function __construct(string $message = 'Service unavailable')
    {
        parent::__construct($message, 503);
    }
}
