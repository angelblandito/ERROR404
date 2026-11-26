<?php
declare(strict_types=1);

namespace Apicore\Core;

/**
 * What an endpoint or middleware returns: status code, payload and headers.
 *
 * Layer: core. It never writes to the socket; Core\Application serializes the
 * payload and sends it.
 */
final class Response
{
    /**
     * @param mixed                $payload array serialized as JSON, or null for no body
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status = 200,
        public mixed $payload = null,
        public array $headers = [],
    ) {
    }

    /**
     * Uniform success body of the store routes: {"status": "ok", "data": ...}.
     *
     * @param array<string, string> $headers
     */
    public static function ok(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($status, ['status' => 'ok', 'data' => $data], $headers);
    }

    /**
     * Like ok(), with an ETag: when the client already has this exact body
     * (If-None-Match), the answer is a 304 without body and the page reuses
     * its copy (the catalog is the largest response of the store).
     *
     * @param array<string, string> $requestHeaders lowercase names
     */
    public static function cached(mixed $data, array $requestHeaders): self
    {
        $payload = ['status' => 'ok', 'data' => $data];
        $etag = 'W/"' . substr(hash('sha256', (string) json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)), 0, 32) . '"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'no-cache'];
        $sent = (string) ($requestHeaders['if-none-match'] ?? '');
        foreach (array_map('trim', explode(',', $sent)) as $candidate) {
            $bare = preg_replace('#^W/#', '', $candidate);
            if ($candidate === '*' || ($candidate !== '' && $bare === preg_replace('#^W/#', '', $etag))) {
                return new self(304, null, $headers);
            }
        }
        return new self(200, $payload, $headers);
    }

    /** Uniform error body: {"error": message}. */
    public static function error(int $status, string $message): self
    {
        return new self($status, ['error' => $message]);
    }
}
