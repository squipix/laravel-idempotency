<?php
namespace squipix\Idempotency\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use squipix\Idempotency\Metrics\MetricsCollector;

class IdempotentJobMiddleware
{
    protected MetricsCollector $metrics;

    public function __construct()
    {
        $this->metrics = app(MetricsCollector::class);
    }

    public function handle($job, $next)
    {
        // Check if queue idempotency is enabled
        if (!config('idempotency.queue.enabled', true)) {
            return $next($job);
        }

        $key = method_exists($job, 'idempotencyKey')
            ? $job->idempotencyKey()
            : null;

        if (!$key) {
            return $next($job);
        }

        $cacheKey = "job-idempotency:{$key}";
        $lockKey = "job-idempotency:{$key}:lock";

        // Check if already processed
        if (Cache::has($cacheKey)) {
            $this->metrics->incrementJobSkipped();
            Log::info('Job skipped due to idempotency', [
                'job' => get_class($job),
                'key' => $key
            ]);
            return;
        }

        // Acquire lock to prevent concurrent execution
        $lock = Cache::lock($lockKey, 60);

        if (!$lock->get()) {
            Log::warning('Job already running', [
                'job' => get_class($job),
                'key' => $key
            ]);
            return;
        }

        try {
            // Double-check after acquiring lock
            if (Cache::has($cacheKey)) {
                return;
            }

            // Execute the job
            $result = $next($job);

            // Mark as processed
            $ttl = config('idempotency.queue.ttl', 86400);
            Cache::put($cacheKey, [
                'processed_at' => now()->toIso8601String(),
                'job_class' => get_class($job),
            ], $ttl);

            $this->metrics->incrementJobExecuted('success');

            return $result;
        } catch (\Throwable $e) {
            $this->metrics->incrementJobExecuted('failed');
            $this->metrics->incrementError('job_failed');

            // Don't mark as processed if job failed
            Cache::forget($cacheKey);

            Log::error('Job failed, idempotency key cleared', [
                'job' => get_class($job),
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
