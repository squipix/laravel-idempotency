<?php
namespace squipix\Idempotency\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use squipix\Idempotency\Services\IdempotencyService;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header(config('idempotency.header'));

        if (!$key) {
            return response()->json(['message' => 'Idempotency-Key required'], 400);
        }

        $service = app(IdempotencyService::class);
        $payloadHash = $service->payloadHash($request);

        // Redis replay
        if ($cached = Cache::get($service->responseKey($key))) {
            return $cached;
        }

        $lock = Cache::lock($service->lockKey($key), config('idempotency.lock_ttl'));

        if (!$lock->get()) {
            return response()->json(['message' => 'Request in progress'], 409);
        }

        try {
            $record = DB::table('idempotency_keys')
                ->where([
                    'key' => $key,
                    'method' => $request->method(),
                    'route' => $request->path(),
                ])->first();

            if ($record) {
                if (
                    config('idempotency.reject_payload_mismatch') &&
                    $record->payload_hash !== $payloadHash
                ) {
                    return response()->json([
                        'message' => 'Payload mismatch for idempotency key'
                    ], 422);
                }

                return response()->json(
                    json_decode($record->response, true),
                    $record->status_code
                );
            }

            $response = $next($request);

            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'method' => $request->method(),
                'route' => $request->path(),
                'payload_hash' => $payloadHash,
                'response' => $response->getContent(),
                'status_code' => $response->getStatusCode(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::put(
                $service->responseKey($key),
                $response,
                now()->addSeconds(config('idempotency.response_ttl'))
            );

            return $response;

        } finally {
            $lock->release();
        }
    }
}
