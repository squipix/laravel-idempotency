<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Squipix\Idempotency\Metrics\MetricsCollector;

class MetricsCollectorTest extends TestCase
{
    public function test_metrics_methods_do_not_throw()
    {
        $collector = new MetricsCollector();
        $collector->increment('idempotency.cache_hit');
        $collector->increment('idempotency.cache_miss');
        $collector->observe('idempotency.request_duration', 123);
        $this->assertTrue(true); // If no exception, test passes
    }
}
