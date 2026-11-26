<?php
/**
 * Controller de rutas propias (las que no son CRUD de una tabla).
 * Layer: controllers. Recibe del router el actor ya autenticado, el token, la consulta y la IP;
 * lee el cuerpo y los parametros, llama al servicio y devuelve datos. No escribe SQL ni HTTP.
 */
abstract class ApiController
{
    public ?string $auth_token = null;
    public ?array $auth_user = null;
    public array $query = [];
    public string $client_ip = '';

    /** Parametro de consulta como texto, o null si no viene. */
    protected function query_text(string $name): ?string
    {
        $value = $this->query[$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Cuerpo JSON como objeto ([] si viene vacio). */
    protected function body(string $body): array
    {
        return json_object($body);
    }

    public function close(): void
    {
    }
}
