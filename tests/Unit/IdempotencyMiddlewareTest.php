<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Squipix\Idempotency\Middleware\IdempotencyMiddleware;
use Squipix\Idempotency\Services\IdempotencyService;
use Squipix\Idempotency\Metrics\MetricsCollector;
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
        $metrics = $this->app->make(MetricsCollector::class);
        $middleware = new IdempotencyMiddleware($service, $metrics);

        $request = Request::create('/test', 'POST', ['foo' => 'bar'], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'abc-123']);

        // Cache the response
        Cache::put($service->responseKey('abc-123'), [
            'content' => json_encode(['result' => 'ok']),
            'status' => 200,
            'headers' => [],
        ], 60);

        $result = $middleware->handle($request, function () {
            return new Response(['result' => 'should-not-run']);
        });

        $this->assertEquals(['result' => 'ok'], json_decode($result->getContent(), true));
    }
}
