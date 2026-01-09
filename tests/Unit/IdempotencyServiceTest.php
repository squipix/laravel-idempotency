<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Squipix\Idempotency\Services\IdempotencyService;
use Illuminate\Support\Facades\DB;

class IdempotencyServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Squipix\Idempotency\IdempotencyServiceProvider::class];
    }

    public function test_can_store_and_retrieve_idempotency_key()
    {
        $service = $this->app->make(IdempotencyService::class);
        $key = 'test-key-123';
        $payload = ['foo' => 'bar'];
        $response = ['result' => 'ok'];
        $ttl = 60;

        $service->storeResponse($key, $payload, $response, $ttl);
        $retrieved = $service->getResponse($key, $payload);

        $this->assertEquals($response, $retrieved);
    }

    public function test_payload_mismatch_returns_null()
    {
        $service = $this->app->make(IdempotencyService::class);
        $key = 'test-key-456';
        $payload1 = ['foo' => 'bar'];
        $payload2 = ['foo' => 'baz'];
        $response = ['result' => 'ok'];
        $ttl = 60;

        $service->storeResponse($key, $payload1, $response, $ttl);
        $retrieved = $service->getResponse($key, $payload2);

        $this->assertNull($retrieved);
    }
}
