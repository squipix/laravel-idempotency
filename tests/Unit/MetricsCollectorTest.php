<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Squipix\Idempotency\Metrics\MetricsCollector;

class MetricsCollectorTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Squipix\Idempotency\IdempotencyServiceProvider::class];
    }

    public function test_metrics_methods_do_not_throw()
    {
        $collector = new MetricsCollector();
        $collector->incrementCacheHit('redis');
        $collector->incrementCacheMiss();
        $collector->recordRequestDuration(123, 'cache_hit');
        $collector->incrementJobExecuted('success');
        $this->expectNotToPerformAssertions();
    }
}
