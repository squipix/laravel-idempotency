<?php
namespace squipix\Idempotency\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;

class IdempotencyService
{
    public function __construct(
        protected CacheRepository $cache,
        protected Connection $db
    ) {}

    public function responseKey(string $key): string
    {
        return "idempotency:$key:response";
    }

    public function lockKey(string $key): string
    {
        return "idempotency:$key:lock";
    }

    public function payloadHash(Request $request): string
    {
        return hash('sha256', json_encode($request->all()));
    }
}
