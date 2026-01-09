<?php

namespace Squipix\Idempotency\Tests\Integration;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Squipix\Idempotency\IdempotencyServiceProvider;

class IdempotencyFeatureTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [IdempotencyServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('idempotency')->post('/test-payment', function (Request $request) {
            return response()->json(['ok' => true, 'payload' => $request->all()]);
        });
    }

    public function test_idempotent_post_returns_same_response()
    {
        $payload = ['foo' => 'bar'];
        $headers = ['Idempotency-Key' => 'test-key-789'];

        $first = $this->postJson('/test-payment', $payload, $headers);
        $second = $this->postJson('/test-payment', $payload, $headers);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertEquals($first->json(), $second->json());
    }

    public function test_different_payloads_with_same_key_return_422()
    {
        $headers = ['Idempotency-Key' => 'test-key-999'];
        $first = $this->postJson('/test-payment', ['foo' => 'bar'], $headers);
        $second = $this->postJson('/test-payment', ['foo' => 'baz'], $headers);

        $first->assertStatus(200);
        $second->assertStatus(422);
    }
}
