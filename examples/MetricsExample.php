<?php

/*
|--------------------------------------------------------------------------
| Example: Prometheus Metrics Endpoint
|--------------------------------------------------------------------------
|
| This file demonstrates how to expose idempotency metrics via Prometheus
|
*/

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    /**
     * Expose Prometheus metrics
     *
     * Route: GET /metrics
     * Middleware: ['auth.basic'] (recommended)
     */
    public function prometheus(): Response
    {
        try {
            $registry = app('prometheus');
            $renderer = new RenderTextFormat();
            $metrics = $renderer->render($registry->getMetricFamilySamples());

            return response($metrics)
                ->header('Content-Type', RenderTextFormat::MIME_TYPE);

        } catch (\Throwable $e) {
            return response('# Metrics unavailable', 503)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Get idempotency metrics summary
     *
     * Route: GET /api/metrics/idempotency
     * Middleware: ['auth:sanctum']
     */
    public function summary()
    {
        $metrics = app(\Squipix\Idempotency\Metrics\MetricsCollector::class);

        return response()->json([
            'metrics' => $metrics->getMetricsSummary(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Example: Routes Configuration
|--------------------------------------------------------------------------
*/

// routes/web.php or routes/api.php

use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

// Prometheus endpoint (protect with basic auth)
Route::get('/metrics', [MetricsController::class, 'prometheus'])
    ->middleware('auth.basic');

// API endpoint for internal monitoring
Route::get('/api/metrics/idempotency', [MetricsController::class, 'summary'])
    ->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Example: Prometheus Configuration
|--------------------------------------------------------------------------
*/

// app/Providers/AppServiceProvider.php

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Prometheus\Storage\InMemory;

public function register()
{
    // Redis adapter (recommended for production)
    $this->app->singleton('prometheus', function ($app) {
        Redis::setDefaultOptions([
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password'),
            'database' => config('database.redis.default.database', 0),
            'timeout' => 0.1,
        ]);

        return new CollectorRegistry(new Redis());
    });

    // Or use in-memory adapter (for testing)
    // $this->app->singleton('prometheus', function ($app) {
    //     return new CollectorRegistry(new InMemory());
    // });
}

/*
|--------------------------------------------------------------------------
| Example: Laravel Pulse Custom Card
|--------------------------------------------------------------------------
*/

namespace App\Livewire\Pulse;

use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class IdempotencyCard extends Card
{
    public function render()
    {
        $metrics = app(\Squipix\Idempotency\Metrics\MetricsCollector::class);
        $summary = $metrics->getMetricsSummary();

        return view('pulse.idempotency', [
            'summary' => $summary,
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Example: Pulse Card View
|--------------------------------------------------------------------------
*/

// resources/views/pulse/idempotency.blade.php

/*
<x-pulse::card>
    <x-pulse::card-header name="Idempotency Metrics" />

    <x-pulse::scroll>
        <div class="grid grid-cols-2 gap-4">
            <div class="p-4 bg-gray-100 rounded">
                <div class="text-sm text-gray-600">Cache Hits</div>
                <div class="text-2xl font-bold">{{ $summary['cache_hits'] ?? 0 }}</div>
            </div>

            <div class="p-4 bg-gray-100 rounded">
                <div class="text-sm text-gray-600">Cache Misses</div>
                <div class="text-2xl font-bold">{{ $summary['cache_misses'] ?? 0 }}</div>
            </div>

            <div class="p-4 bg-gray-100 rounded">
                <div class="text-sm text-gray-600">Jobs Executed</div>
                <div class="text-2xl font-bold">{{ $summary['jobs_executed'] ?? 0 }}</div>
            </div>

            <div class="p-4 bg-gray-100 rounded">
                <div class="text-sm text-gray-600">Jobs Skipped</div>
                <div class="text-2xl font-bold">{{ $summary['jobs_skipped'] ?? 0 }}</div>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
*/

/*
|--------------------------------------------------------------------------
| Example: Grafana Dashboard Query Examples
|--------------------------------------------------------------------------
*/

// Cache Hit Ratio (Percentage)
// sum(rate(idempotency_cache_hits_total[5m])) /
// (sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m]))) * 100

// Average Response Time
// rate(idempotency_request_duration_seconds_sum[5m]) /
// rate(idempotency_request_duration_seconds_count[5m])

// 95th Percentile Response Time
// histogram_quantile(0.95, rate(idempotency_request_duration_seconds_bucket[5m]))

// Requests per Second
// sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m]))

// Error Rate
// rate(idempotency_errors_total[5m])

/*
|--------------------------------------------------------------------------
| Example: Alertmanager Configuration
|--------------------------------------------------------------------------
*/

/*
# alertmanager.yml

route:
  receiver: 'team-email'
  group_by: ['alertname', 'severity']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 12h
  routes:
    - match:
        service: idempotency
      receiver: 'idempotency-alerts'

receivers:
  - name: 'idempotency-alerts'
    slack_configs:
      - api_url: 'YOUR_SLACK_WEBHOOK_URL'
        channel: '#idempotency-alerts'
        title: 'Idempotency Alert'
        text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'

    email_configs:
      - to: 'team@example.com'
        from: 'alerts@example.com'
        smarthost: 'smtp.example.com:587'
        auth_username: 'alerts@example.com'
        auth_password: 'password'

*/

/*
|--------------------------------------------------------------------------
| Example: Custom Health Check Endpoint
|--------------------------------------------------------------------------
*/

// routes/api.php
Route::get('/health/idempotency', function () {
    $metrics = app(\Squipix\Idempotency\Metrics\MetricsCollector::class);
    $summary = $metrics->getMetricsSummary();

    if (!$summary['enabled']) {
        return response()->json([
            'status' => 'disabled',
            'message' => 'Metrics collection is disabled'
        ], 200);
    }

    $cacheTotal = $summary['cache_hits'] + $summary['cache_misses'];
    $hitRatio = $cacheTotal > 0
        ? ($summary['cache_hits'] / $cacheTotal) * 100
        : 0;

    $healthy = $hitRatio >= 70 && $summary['errors'] < 10;

    return response()->json([
        'status' => $healthy ? 'healthy' : 'degraded',
        'cache_hit_ratio' => round($hitRatio, 2),
        'errors' => $summary['errors'],
        'checks' => [
            'cache_hit_ratio' => $hitRatio >= 70 ? 'pass' : 'fail',
            'error_count' => $summary['errors'] < 10 ? 'pass' : 'fail',
        ]
    ], $healthy ? 200 : 503);
});

/*
|--------------------------------------------------------------------------
| Example: Testing Metrics
|--------------------------------------------------------------------------
*/

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Squipix\Idempotency\Metrics\MetricsCollector;

class IdempotencyMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_track_cache_hits()
    {
        $metrics = app(MetricsCollector::class);
        $before = $metrics->getMetricsSummary();

        // Make idempotent request twice
        $key = 'test-' . uniqid();

        $this->postJson('/api/payments',
            ['amount' => 1000],
            ['Idempotency-Key' => $key]
        );

        $this->postJson('/api/payments',
            ['amount' => 1000],
            ['Idempotency-Key' => $key]
        );

        $after = $metrics->getMetricsSummary();

        // Should have 1 cache hit
        $this->assertEquals(
            $before['cache_hits'] + 1,
            $after['cache_hits']
        );
    }

    public function test_metrics_track_payload_mismatch()
    {
        $metrics = app(MetricsCollector::class);
        $before = $metrics->getMetricsSummary();

        $key = 'test-' . uniqid();

        $this->postJson('/api/payments',
            ['amount' => 1000],
            ['Idempotency-Key' => $key]
        )->assertStatus(201);

        $this->postJson('/api/payments',
            ['amount' => 2000], // Different amount
            ['Idempotency-Key' => $key]
        )->assertStatus(422);

        $after = $metrics->getMetricsSummary();

        $this->assertEquals(
            $before['payload_mismatches'] + 1,
            $after['payload_mismatches']
        );
    }
}
