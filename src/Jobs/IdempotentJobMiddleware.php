<?php
namespace squipix\Idempotency\Jobs;

use Illuminate\Support\Facades\Cache;

class IdempotentJobMiddleware
{
    public function handle($job, $next)
    {
        $key = method_exists($job, 'idempotencyKey')
            ? $job->idempotencyKey()
            : null;

        if (!$key) {
            return $next($job);
        }

        $cacheKey = "job-idempotency:$key";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addDay());

        try {
            $next($job);
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }
    }
}
