<?php
/**
 * Traduccion de core/logger.py: una linea por evento con la hora en APP_TIMEZONE.
 * Layer: core (cross-cutting). Todo el proyecto escribe por aqui.
 */
function app_log(string $level, string $message): void
{
    $now = (new DateTimeImmutable('now', DateUtils::local_tz()))->format('Y-m-d H:i:s');
    error_log("$now $level $message");
}

function log_info(string $message): void
{
    app_log('INFO', $message);
}

function log_exception(string $message): void
{
    app_log('ERROR', $message);
}
