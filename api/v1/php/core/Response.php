<?php
/*
 * Manejo uniforme de errores de la API.
 *
 * Los modelos (models/*.php) no imprimen errores: dejan el error en $DB_Error
 * y index.php llama a api_fail_if_error() justo después de incluirlos.
 * El detalle técnico de SQL Server se escribe en el log del servidor; al
 * cliente solo le llega un mensaje claro y el código HTTP adecuado.
 */

function api_error(int $status, string $message): array
{
    return ['status' => $status, 'message' => $message];
}

function api_sql_message(PDOException $ex): string
{
    $text = $ex->getMessage();
    $pos = strrpos($text, ']');
    return trim($pos !== false ? substr($text, $pos + 1) : $text);
}

/* Traduce el número de error nativo de SQL Server a una respuesta HTTP. */
function api_db_error(PDOException $ex): array
{
    error_log('Error SQL: ' . $ex->getMessage());

    $code = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : 0;

    if ($code >= 50000) {
        /* Errores de negocio lanzados con THROW en los procedimientos. */
        return api_error(409, api_sql_message($ex));
    }

    switch ($code) {
        case 2601:
        case 2627:
            return api_error(409, 'Ya existe un registro con esos datos (valor duplicado).');
        case 547:
            return api_error(409, 'La operación viola una regla de integridad: una referencia no existe, el registro está en uso o un valor no es válido.');
        case 515:
            return api_error(400, 'Falta un campo obligatorio.');
        case 241:
        case 242:
        case 245:
        case 8114:
        case 8115:
        case 8152:
        case 2628:
            return api_error(400, 'Un campo tiene un formato, tipo o longitud no válidos.');
        case 1205:
            return api_error(503, 'La operación se canceló por un conflicto de concurrencia. Intente de nuevo.');
        default:
            return api_error(500, 'Error interno de la base de datos.');
    }
}

function api_send_error(array $error): void
{
    http_response_code($error['status']);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => $error['message'],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function api_fail_if_error(): void
{
    global $DB_Error;
    if ($DB_Error !== null) {
        api_send_error($DB_Error);
        exit;
    }
}

/* Lee los parámetros de paginación: ?page=1&page_size=100 (máximo 500). */
function api_page(): array
{
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $size = isset($_GET['page_size']) ? min(500, max(1, (int)$_GET['page_size'])) : 100;
    return [($page - 1) * $size, $size];
}
