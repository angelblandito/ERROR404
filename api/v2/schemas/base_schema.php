<?php
/**
 * Traduccion de schemas/base_schema.py: validadores reutilizables que usa cada schema de entidad.
 * Layer: schemas (cross-cutting). Solo funciones sobre un array: sin HTTP, sin SQL, sin base de datos.
 * Cada schema de entidad (clase con validate() estatico) combina estas funciones, igual que en el profe.
 *
 * Mismas reglas que base_schema.py: bool no es entero ni numero; DECIMAL/FLOAT aceptan int o float;
 * fechas en formato estricto. Lanzan ValueError (400 en el error middleware).
 */

/** Rechaza campos que no existen en la entidad (400, no 500 por una columna desconocida). */
function reject_unknown_fields(array $data, array $allowed): void
{
    $unknown = array_diff(array_keys($data), $allowed);
    if ($unknown !== []) {
        throw new ValueError('Unknown field: ' . implode(', ', $unknown));
    }
}

/** Lanza ValueError salvo que data[field] sea una de las opciones. partial=true omite el campo ausente. */
function validate_enum(array $data, string $field, array $options, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    if (!in_array($data[$field] ?? null, $options, true)) {
        throw new ValueError("$field must be " . implode(' or ', array_map(static fn($o) => "\"$o\"", $options)));
    }
}

/** Lanza ValueError si data[field] no es string dentro de max_length. required rechaza vacio. */
function validate_string(array $data, string $field, ?int $max_length = null, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value !== null && !is_string($value)) {
        throw new ValueError("$field must be a string");
    }
    if ($required && ($value === null || $value === '')) {
        throw new ValueError("$field is required");
    }
    if ($value !== null && $value !== '' && $max_length !== null && mb_strlen($value) > $max_length) {
        throw new ValueError("$field must be $max_length chars max");
    }
}

/** Texto de cualquier longitud (columna TEXT). */
function validate_text(array $data, string $field, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value !== null && !is_string($value)) {
        throw new ValueError("$field must be a string");
    }
    if ($required && ($value === null || $value === '')) {
        throw new ValueError("$field is required");
    }
}

/** Entero dentro del rango. bool se rechaza a proposito (en PHP true tambien es int). */
function validate_integer(array $data, string $field, ?int $minimum = null, ?int $maximum = null, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (is_bool($value) || !is_int($value)) {
        throw new ValueError("$field must be an integer");
    }
    if ($minimum !== null && $value < $minimum) {
        throw new ValueError("$field must be $minimum or more");
    }
    if ($maximum !== null && $value > $maximum) {
        throw new ValueError("$field must be $maximum or less");
    }
}

/** Entero o decimal dentro del rango (DECIMAL y FLOAT). La base redondea a la escala de la columna. */
function validate_number(array $data, string $field, int|float|null $minimum = null, int|float|null $maximum = null, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (is_bool($value) || !(is_int($value) || is_float($value))) {
        throw new ValueError("$field must be a number");
    }
    if ($minimum !== null && $value < $minimum) {
        throw new ValueError("$field must be $minimum or more");
    }
    if ($maximum !== null && $value > $maximum) {
        throw new ValueError("$field must be $maximum or less");
    }
}

/** true o false (columna BIT / tinyint(1)). */
function validate_boolean(array $data, string $field, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (!is_bool($value)) {
        throw new ValueError("$field must be true or false");
    }
}

/** Comun a date, datetime y time: el texto debe reproducirse igual al formatear (formato estricto). */
function validate_iso(array $data, string $field, string $format, string $label, string $example, bool $required, bool $partial): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (!is_string($value)) {
        throw new ValueError("$field must be a string");
    }
    $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
    if ($parsed === false || $parsed->format($format) !== $value) {
        throw new ValueError("$field must be a $label like $example");
    }
}

/** 'YYYY-MM-DD' (columna DATE). */
function validate_date(array $data, string $field, bool $required = false, bool $partial = false): void
{
    validate_iso($data, $field, 'Y-m-d', 'date', '2026-01-31', $required, $partial);
}

/** 'YYYY-MM-DD HH:MM:SS' (columna DATETIME). */
function validate_datetime(array $data, string $field, bool $required = false, bool $partial = false): void
{
    validate_iso($data, $field, 'Y-m-d H:i:s', 'datetime', '2026-01-31 08:30:00', $required, $partial);
}

/** 'HH:MM:SS' (columna TIME). */
function validate_time(array $data, string $field, bool $required = false, bool $partial = false): void
{
    validate_iso($data, $field, 'H:i:s', 'time', '08:30:00', $required, $partial);
}

/** Anio de cuatro digitos (columna YEAR). Limites de MySQL YEAR(4), igual que el profe. */
function validate_year(array $data, string $field, int $minimum = 1901, int $maximum = 2155, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (is_bool($value) || !is_int($value)) {
        throw new ValueError("$field must be a four-digit year");
    }
    if ($value < $minimum || $value > $maximum) {
        throw new ValueError("$field must be a four-digit year between $minimum and $maximum");
    }
}

/** Objeto o lista JSON (columna JSON). En PHP ambos son array. */
function validate_json(array $data, string $field, bool $required = false, bool $partial = false): void
{
    if ($partial && !array_key_exists($field, $data)) {
        return;
    }
    $value = $data[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new ValueError("$field is required");
        }
        return;
    }
    if (!is_array($value)) {
        throw new ValueError("$field must be a JSON object or array");
    }
}
