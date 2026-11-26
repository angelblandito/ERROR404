<?php
/**
 * Traduccion de utils/date_utils.py. Los datos se guardan en UTC (DATETIME2 no tiene zona)
 * y se muestran en APP_TIMEZONE (America/Tijuana por defecto).
 * Nunca usar la hora de la maquina directamente: siempre DateUtils.
 * Layer: utils (cross-cutting).
 */
final class DateUtils
{
    public static function local_tz(): DateTimeZone
    {
        return new DateTimeZone(Settings::instance()->APP_TIMEZONE);
    }

    /** Hora actual en UTC, como texto para columnas DATETIME2. */
    public static function utc_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /** Epoch en segundos -> texto UTC para DATETIME2. */
    public static function utc_from_timestamp(int $epoch): string
    {
        return (new DateTimeImmutable('@' . $epoch))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Texto UTC de la base -> ISO 8601 en APP_TIMEZONE, p. ej. 2026-11-14T15:04:05-08:00. */
    public static function isoformat_local(string $utc_value): string
    {
        return (new DateTimeImmutable($utc_value, new DateTimeZone('UTC')))
            ->setTimezone(self::local_tz())
            ->format(DateTimeInterface::ATOM);
    }

    /** Epoch en segundos -> ISO 8601 en APP_TIMEZONE. */
    public static function isoformat_from_timestamp(int $epoch): string
    {
        return (new DateTimeImmutable('@' . $epoch))->setTimezone(self::local_tz())->format(DateTimeInterface::ATOM);
    }
}
