<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Squipix\Idempotency\Services\IdempotencyService;
use Illuminate\Support\Facades\DB;

class IdempotencyServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Squipix\Idempotency\IdempotencyServiceProvider::class];
    }

    public function test_generates_response_key()
    {
        $service = $this->app->make(IdempotencyService::class);
        $key = 'test-key-123';

        $responseKey = $service->responseKey($key);

        $this->assertEquals('idempotency:test-key-123:response', $responseKey);
    }

    public function test_generates_lock_key()
    {
        $service = $this->app->make(IdempotencyService::class);
        $key = 'test-key-456';

        $lockKey = $service->lockKey($key);

        $this->assertEquals('idempotency:test-key-456:lock', $lockKey);
    }

    public function test_generates_consistent_payload_hash()
    {
        $service = $this->app->make(IdempotencyService::class);
        $request1 = Request::create('/test', 'POST', ['foo' => 'bar', 'baz' => 123]);
        $request2 = Request::create('/test', 'POST', ['baz' => 123, 'foo' => 'bar']);

        $hash1 = $service->payloadHash($request1);
        $hash2 = $service->payloadHash($request2);

        $this->assertEquals($hash1, $hash2);
    }
}
