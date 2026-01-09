<?php

namespace Squipix\Idempotency\Contracts;

interface IdempotencyStore
{
    public function get(string $key, string $method, string $route);
    public function save(string $key, string $method, string $route, string $payloadHash, $response, int $statusCode);
}
