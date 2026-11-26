<?php
/**
 * Traduccion de core/exceptions.py: errores de aplicacion con su codigo HTTP.
 * Layer: core. Los traduce middlewares/error_middleware.php.
 */
class AppError extends RuntimeException
{
    protected int $status_code = 500;

    public function __construct(string $message, ?int $status_code = null)
    {
        parent::__construct($message);
        if ($status_code !== null) {
            $this->status_code = $status_code;
        }
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }
}

class UnauthorizedError extends AppError { protected int $status_code = 401; }
class ForbiddenError extends AppError { protected int $status_code = 403; }
class PayloadTooLargeError extends AppError { protected int $status_code = 413; }
class TooManyRequestsError extends AppError { protected int $status_code = 429; }
class ServiceUnavailableError extends AppError { protected int $status_code = 503; }

/** Error con un codigo HTTP elegido por el servicio (404, 409, 410, 422...). */
final class HttpError extends AppError
{
    public function __construct(int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}

/** Estado HTTP de una excepcion: AppError conserva el suyo, ValueError e InvalidArgumentException son 400, lo demas 500. */
function status_of(Throwable $error): int
{
    if ($error instanceof AppError) {
        return $error->getStatusCode();
    }
    if ($error instanceof ValueError || $error instanceof InvalidArgumentException) {
        return 400;
    }
    return 500;
}
