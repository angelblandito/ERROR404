<?php
/** Traduccion de tests/test_response.py: el objeto Response (sobre de error y valores por defecto). */

function test_response_defaults(): void
{
    $response = new Response();
    assert_same([200, null, []], [$response->status, $response->payload, $response->headers]);
}

function test_response_error_builds_uniform_body(): void
{
    $response = Response::error(404, 'Not found');
    assert_same(404, $response->status);
    assert_same(['success' => false, 'data' => null, 'message' => 'Not found'], $response->payload);
}

function test_response_headers_are_not_shared(): void
{
    $first = new Response();
    $second = new Response();
    $first->headers['Allow'] = 'GET';
    assert_same([], $second->headers);
}
