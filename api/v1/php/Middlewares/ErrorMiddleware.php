<?php
declare(strict_types=1);

namespace Apicore\Middlewares;

use Apicore\Core\AppError;
use Apicore\Core\Logger;
use Apicore\Core\Request;
use Apicore\Core\Response;
use Closure;
use PDOException;
use Throwable;
use ValueError;

/**
 * Translate exceptions into Response objects, the pipeline's response shape.
 *
 * Layer: middlewares (cross-cutting). Sits inside CorsMiddleware, so every
 * error leaves the pipeline as a uniform JSON body that still carries the CORS
 * headers.
 */
final class ErrorMiddleware extends Middleware
{
    /**
     * AppError keeps its status code, ValueError is a 400, a SQL Server error is
     * translated by AppError::fromDatabase() and anything else is a 500. The
     * detail is always logged and never returned to the client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (AppError | ValueError $error) {
            return Response::error(AppError::statusOf($error), $error->getMessage());
        } catch (PDOException $error) {
            // The SQL text never reaches the client: only the translated message
            Logger::exception('SQL error on ' . $request->method . ' ' . $request->path . ': ' . $error->getMessage());
            $translated = AppError::fromDatabase($error);
            return Response::error($translated->statusCode(), $translated->getMessage());
        } catch (Throwable $error) {
            Logger::exception('Unhandled error on ' . $request->method . ' ' . $request->path . ': '
                . $error::class . ': ' . $error->getMessage());
            return Response::error(500, 'Internal server error');
        }
    }
}
