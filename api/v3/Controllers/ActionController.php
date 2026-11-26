<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\Router;
use ValueError;

/**
 * Base of the controllers for the store routes that are not the CRUD of one
 * table (catalog, checkout, admin panel, distributed database...).
 *
 * Layer: controllers. No model (MODEL_CLASS = null, like HealthController): the
 * work is done by the services. It only reads the body and the query string
 * and gathers the response headers the router sends (X-Total-Count).
 */
abstract class ActionController extends BaseController
{
    protected const MODEL_CLASS = null;

    /** @var array<string, string> extra headers for the response; the router adds them */
    public array $responseHeaders = [];

    /**
     * JSON body as an object ([] when the body is empty).
     *
     * @return array<string, mixed>
     */
    protected function body(string $body): array
    {
        return trim($body) === '' ? [] : Router::jsonObject($body);
    }

    /** Query-string parameter as text, or null when it is missing. */
    protected function queryText(string $name): ?string
    {
        $value = $this->query[$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Optional pagination with ?page=N&size=M (the same names as the CRUD).
     * Without page the whole list is returned; with page only that page and
     * the total in the X-Total-Count header.
     *
     * @param list<mixed> $rows
     * @return list<mixed>
     */
    protected function pageRows(array $rows): array
    {
        $query = $this->query ?? [];
        $page = Router::positiveInt($query, 'page');
        if ($page === 0) {
            return $rows;
        }
        $size = Router::positiveInt($query, 'size') ?: 20;
        if ($size > Router::MAX_PAGE_SIZE) {
            throw new ValueError('size must be ' . Router::MAX_PAGE_SIZE . ' or less');
        }
        $this->responseHeaders['X-Total-Count'] = (string) count($rows);
        return array_slice(array_values($rows), ($page - 1) * $size, $size);
    }
}
