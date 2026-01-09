<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Squipix\Idempotency\Middleware\IdempotencyMiddleware;
use Squipix\Idempotency\Services\IdempotencyService;
use Illuminate\Http\Response;

class IdempotencyMiddlewareTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Squipix\Idempotency\IdempotencyServiceProvider::class];
    }

    public function test_middleware_returns_cached_response()
    {
        $service = $this->app->make(IdempotencyService::class);
        $middleware = new IdempotencyMiddleware($service, null);
        $request = Request::create('/test', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'abc-123']);
        $payload = ['foo' => 'bar'];
        $response = new Response(['result' => 'ok']);
        $service->storeResponse('abc-123', $payload, ['result' => 'ok'], 60);

        $result = $middleware->handle($request, function () {
            return new Response(['result' => 'should-not-run']);
        });

        $this->assertEquals(['result' => 'ok'], $result->getOriginalContent());
    }
}
