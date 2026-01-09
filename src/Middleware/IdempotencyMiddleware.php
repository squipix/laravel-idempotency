<?php
namespace squipix\Idempotency\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use squipix\Idempotency\Services\IdempotencyService;
use squipix\Idempotency\Metrics\MetricsCollector;

class IdempotencyMiddleware
{
    public function __construct(
        protected IdempotencyService $service,
        protected MetricsCollector $metrics
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Only apply to non-GET requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        $key = $request->header(config('idempotency.header'));

        if (!$key) {
            return response()->json(['message' => 'Idempotency-Key required'], 400);
        }

        // Validate key format
        if (!$this->isValidKey($key)) {
            return response()->json(['message' => 'Invalid Idempotency-Key format'], 400);
        }

        $payloadHash = $this->service->payloadHash($request);

        // Redis replay
        if ($cached = Cache::get($this->service->responseKey($key))) {
            $this->metrics->incrementCacheHit('redis');
            $duration = microtime(true) - $startTime;
            $this->metrics->recordRequestDuration($duration, 'cache_hit');
            return $this->restoreResponse($cached);
        }

        $lock = Cache::lock($this->service->lockKey($key), config('idempotency.lock_ttl'));

        if (!$lock->get()) {
            $this->metrics->incrementLockFailed();
            return response()->json(['message' => 'Request in progress'], 409);
        }

        $this->metrics->incrementLockAcquired();

        try {
            $this->metrics->incrementCacheMiss();

            $record = $this->service->getRecord(
                $key,
                $request->method(),
                $request->path()
            );

            if ($record) {
                if (
                    config('idempotency.reject_payload_mismatch') &&
                    $record->payload_hash !== $payloadHash
                ) {
                    $this->metrics->incrementPayloadMismatch();
                    return response()->json([
                        'message' => 'Payload mismatch for idempotency key'
                    ], 422);
                }

                $this->metrics->incrementCacheHit('database');
                $responseData = json_decode($record->response, true);
                $response = response()->json($responseData, $record->status_code);

                // Cache the DB response for faster replay
                Cache::put(
                    $this->service->responseKey($key),
                    $this->prepareResponseForCache($response),
                    now()->addSeconds(config('idempotency.response_ttl'))
                );

                return $response;
            }

            $response = $next($request);

            // Only cache successful responses (2xx status codes)
            if (!$response->isSuccessful()) {
                $lock->release();
                return $response;
            }

            $this->service->saveRecord(
                $key,
                $request->method(),
                $request->path(),
                $payloadHash,
                $response->getContent(),
                $response->getStatusCode()
            );

            Cache::put(
                $this->service->responseKey($key),
                $this->prepareResponseForCache($response),
                now()->addSeconds(config('idempotency.response_ttl'))
            );

            return $response;
        } catch (\Throwable $e) {
            Log::error('Idempotency middleware error', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    protected function isValidKey(string $key): bool
    {
        // Must be between 1 and 255 characters
        return strlen($key) > 0 && strlen($key) <= 255;
    }

    protected function prepareResponseForCache($response): array
    {
        return [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
        ];
    }

    protected function restoreResponse(array $cached)
    {
        $response = response()->json(
            json_decode($cached['content'], true),
            $cached['status']
        );

        // Restore headers if present
        if (isset($cached['headers'])) {
            foreach ($cached['headers'] as $key => $values) {
                $response->headers->set($key, $values);
            }
        }

        return $response;
    }
}
