<?php

namespace Squipix\Idempotency\Metrics;

use Illuminate\Support\Facades\Cache;

class MetricsCollector
{
    protected bool $enabled;
    protected ?object $prometheusCollector = null;
    protected ?object $pulseRecorder = null;

    public function __construct()
    {
        $this->enabled = config('idempotency.metrics.enabled', false);
        $this->initializeCollectors();
    }

    protected function initializeCollectors(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Initialize Prometheus if available
        if (config('idempotency.metrics.prometheus.enabled', false) && class_exists('\Prometheus\CollectorRegistry')) {
            try {
                $adapter = app(config('idempotency.metrics.prometheus.adapter', 'prometheus'));
                $this->prometheusCollector = $adapter;
            } catch (\Throwable $e) {
                // Prometheus not configured, continue without it
            }
        }

        // Initialize Laravel Pulse if available
        if (config('idempotency.metrics.pulse.enabled', false) && class_exists('\Laravel\Pulse\Facades\Pulse')) {
            $this->pulseRecorder = app('pulse');
        }
    }

    public function incrementCacheHit(string $type = 'redis'): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_cache_hits_total', 1, ['type' => $type]);
        $this->recordToPulse('idempotency.cache.hit', 1, ['type' => $type]);
    }

    public function incrementCacheMiss(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_cache_misses_total', 1);
        $this->recordToPulse('idempotency.cache.miss', 1);
    }

    public function incrementLockAcquired(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_locks_acquired_total', 1);
        $this->recordToPulse('idempotency.lock.acquired', 1);
    }

    public function incrementLockFailed(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_locks_failed_total', 1);
        $this->recordToPulse('idempotency.lock.failed', 1);
    }

    public function incrementPayloadMismatch(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_payload_mismatches_total', 1);
        $this->recordToPulse('idempotency.payload.mismatch', 1);
    }

    public function recordRequestDuration(float $duration, string $status): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_request_duration_seconds', $duration, ['status' => $status], 'histogram');
        $this->recordToPulse('idempotency.request.duration', $duration, ['status' => $status]);
    }

    public function incrementJobExecuted(string $status = 'success'): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_jobs_executed_total', 1, ['status' => $status]);
        $this->recordToPulse('idempotency.job.executed', 1, ['status' => $status]);
    }

    public function incrementJobSkipped(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_jobs_skipped_total', 1);
        $this->recordToPulse('idempotency.job.skipped', 1);
    }

    public function recordDatabaseQuery(string $operation, float $duration): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_database_query_duration_seconds', $duration, ['operation' => $operation], 'histogram');
        $this->recordToPulse('idempotency.database.query', $duration, ['operation' => $operation]);
    }

    public function incrementError(string $type): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->recordToPrometheus('idempotency_errors_total', 1, ['type' => $type]);
        $this->recordToPulse('idempotency.error', 1, ['type' => $type]);
    }

    protected function recordToPrometheus(string $metric, $value, array $labels = [], string $type = 'counter'): void
    {
        if (!$this->prometheusCollector) {
            return;
        }

        try {
            $namespace = config('idempotency.metrics.prometheus.namespace', 'app');

            switch ($type) {
                case 'histogram':
                    if (method_exists($this->prometheusCollector, 'getOrRegisterHistogram')) {
                        $histogram = $this->prometheusCollector->getOrRegisterHistogram(
                            $namespace,
                            $metric,
                            'Idempotency metric',
                            array_keys($labels),
                            config('idempotency.metrics.prometheus.buckets', [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5, 10])
                        );
                        $histogram->observe($value, array_values($labels));
                    }
                    break;

                default: // counter
                    if (method_exists($this->prometheusCollector, 'getOrRegisterCounter')) {
                        $counter = $this->prometheusCollector->getOrRegisterCounter(
                            $namespace,
                            $metric,
                            'Idempotency metric',
                            array_keys($labels)
                        );
                        $counter->incBy($value, array_values($labels));
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Silently fail to not disrupt the application
        }
    }

    protected function recordToPulse(string $type, $value, array $extra = []): void
    {
        if (!$this->pulseRecorder) {
            return;
        }

        try {
            if (class_exists('\\Laravel\Pulse\Facades\Pulse')) {
                // Pulse::record expects int|null as third parameter
                $meta = null;
                if (!empty($extra)) {
                    // If you want to pass meta, you could use count($extra) or null
                    $meta = count($extra);
                }
                \Laravel\Pulse\Facades\Pulse::record($type, $value, $meta);
            }
        } catch (\Throwable $e) {
            // Silently fail to not disrupt the application
        }
    }

    public function getMetricsSummary(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        $cachePrefix = 'idempotency:metrics:';

        return [
            'enabled' => true,
            'cache_hits' => Cache::get($cachePrefix . 'cache_hits', 0),
            'cache_misses' => Cache::get($cachePrefix . 'cache_misses', 0),
            'locks_acquired' => Cache::get($cachePrefix . 'locks_acquired', 0),
            'locks_failed' => Cache::get($cachePrefix . 'locks_failed', 0),
            'payload_mismatches' => Cache::get($cachePrefix . 'payload_mismatches', 0),
            'jobs_executed' => Cache::get($cachePrefix . 'jobs_executed', 0),
            'jobs_skipped' => Cache::get($cachePrefix . 'jobs_skipped', 0),
            'errors' => Cache::get($cachePrefix . 'errors', 0),
        ];
    }

    public function resetMetrics(): void
    {
        if (!$this->enabled) {
            return;
        }

        $cachePrefix = 'idempotency:metrics:';
        $metrics = ['cache_hits', 'cache_misses', 'locks_acquired', 'locks_failed',
                   'payload_mismatches', 'jobs_executed', 'jobs_skipped', 'errors'];

        foreach ($metrics as $metric) {
            Cache::forget($cachePrefix . $metric);
        }
    }
}
