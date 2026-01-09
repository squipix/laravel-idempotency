<?php

namespace Squipix\Idempotency\Services;

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
        return "idempotency:{$key}:response";
    }

    public function lockKey(string $key): string
    {
        return "idempotency:{$key}:lock";
    }

    public function payloadHash(Request $request): string
    {
        // Sort keys for consistent hashing
        $payload = $request->all();
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function getRecord(string $key, string $method, string $route)
    {
        return $this->db->table('idempotency_keys')
            ->where('key', $key)
            ->where('method', $method)
            ->where('route', $route)
            ->first();
    }

    public function saveRecord(string $key, string $method, string $route, string $payloadHash, string $response, int $statusCode): void
    {
        $this->db->table('idempotency_keys')->insert([
            'key' => $key,
            'method' => $method,
            'route' => $route,
            'payload_hash' => $payloadHash,
            'response' => $response,
            'status_code' => $statusCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function cleanupExpiredRecords(int $daysOld = 7): int
    {
        return $this->db->table('idempotency_keys')
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    public function countExpiredRecords(int $daysOld = 7): int
    {
        return $this->db->table('idempotency_keys')
            ->where('created_at', '<', now()->subDays($daysOld))
            ->count();
    }
}
