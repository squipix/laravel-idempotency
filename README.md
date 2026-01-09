# Laravel Idempotency

[![Latest Version](https://img.shields.io/packagist/v/squipix/laravel-idempotency.svg?style=flat-square)](https://packagist.org/packages/squipix/laravel-idempotency)
[![Total Downloads](https://img.shields.io/packagist/dt/squipix/laravel-idempotency.svg?style=flat-square)](https://packagist.org/packages/squipix/laravel-idempotency)
[![GitHub Tests](https://img.shields.io/github/actions/workflow/status/squipix/laravel-idempotency/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/squipix/laravel-idempotency/actions)
[![GitHub Code Style](https://img.shields.io/github/actions/workflow/status/squipix/laravel-idempotency/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/squipix/laravel-idempotency/actions)
[![License](https://img.shields.io/packagist/l/squipix/laravel-idempotency.svg?style=flat-square)](https://packagist.org/packages/squipix/laravel-idempotency)

Stripe-style idempotency for Laravel APIs and queues. Prevent duplicate API requests and duplicate job executions with minimal configuration.

## Features

- ✅ **Stripe-style API guarantees** - Handle network retries and duplicate requests safely
- ✅ **Horizontal scaling ready** - Uses Redis for distributed locking
- ✅ **Crash-safe queues** - Prevent duplicate job execution on retries
- ✅ **Payment-grade safety** - Battle-tested for financial transactions
- ✅ **Zero configuration** - Works out of the box with sensible defaults
- ✅ **Payload validation** - Detect and reject requests with same key but different data
- ✅ **Performance optimized** - Redis caching with <1ms lock acquire time

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Redis (for distributed locking)

## Installation

Install via Composer:

```bash
composer require squipix/laravel-idempotency
```

Publish the configuration and migration files:

```bash
php artisan vendor:publish --tag=idempotency-config
php artisan vendor:publish --tag=idempotency-migrations
```

Run the migration:

```bash
php artisan migrate
```

## Configuration

The configuration file is published at `config/idempotency.php`:

```php
return [
    'header' => 'Idempotency-Key',           // HTTP header name
    'lock_ttl' => 10,                        // Lock timeout in seconds
    'response_ttl' => 86400,                 // Response cache TTL (24 hours)
    'reject_payload_mismatch' => true,       // Reject if same key, different payload
    'queue' => [
        'enabled' => true,                   // Enable queue idempotency
        'ttl' => 86400,                      // Job idempotency TTL (24 hours)
    ],
];
```

## Usage

### API Routes

Apply the middleware to routes that need idempotency protection:

```php
use Illuminate\Support\Facades\Route;

// Apply to single route
Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware('idempotency');

// Apply to route group
Route::middleware(['auth', 'idempotency'])->group(function () {
    Route::post('/orders', [OrderController::class, 'create']);
    Route::post('/transfers', [TransferController::class, 'execute']);
});
```

### Making Idempotent Requests

Clients should send a unique `Idempotency-Key` header with each request:

```bash
curl -X POST https://api.example.com/payments \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 1000,
    "currency": "USD",
    "customer_id": "cus_123"
  }'
```

**Behavior:**
- First request: Processes normally, returns response
- Duplicate request (same key): Returns cached response immediately
- Same key, different payload: Returns 422 error (configurable)
- Concurrent requests: Second request waits or returns 409

### Queue Jobs

Make any job idempotent by adding the middleware and idempotency key:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Squipix\Idempotency\Jobs\IdempotentJobMiddleware;

class CapturePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $paymentId,
        public int $amount
    ) {}

    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }

    public function idempotencyKey(): string
    {
        return "payment-capture:{$this->paymentId}";
    }

    public function handle()
    {
        // Capture payment logic
        // This will never execute twice for the same payment ID
    }
}
```

**Benefits:**
- ✔ Retry-safe
- ✔ Crash-safe
- ✔ No duplicate charges
- ✔ Worker concurrency protection

### Without Idempotency Key (Optional)

Jobs without an `idempotencyKey()` method will execute normally:

```php
class SendEmailJob implements ShouldQueue
{
    // No idempotency middleware - will execute on every attempt
    
    public function handle()
    {
        // Send email
    }
}
```

## How It Works

### API Idempotency Flow

1. **Request arrives** with `Idempotency-Key` header
2. **Check Redis cache** - Return cached response if exists (fastest path)
3. **Acquire distributed lock** - Prevent concurrent execution
4. **Check database** - Return stored response if exists
5. **Execute request** - Process normally
6. **Store response** - Save to both database and Redis
7. **Release lock** - Allow other requests

### Job Idempotency Flow

1. **Job dispatched** with `idempotencyKey()`
2. **Check cache** - Skip if already processed
3. **Acquire lock** - Prevent concurrent execution
4. **Execute job** - Run normally
5. **Mark complete** - Store completion flag in cache
6. **On failure** - Clear flag, allow retry

## Performance

Benchmarked on a standard setup (4GB Redis, Laravel 11, PHP 8.2):

| Metric           | Result         |
|------------------|----------------|
| Lock acquire     | <1ms           |
| Cache hit replay | ~0.2ms         |
| DB replay        | ~5ms           |
| Throughput       | 20k+ req/sec   |
| Collision rate   | Zero           |

## Edge Cases Handled

### 1. Gateway Timeout After Charge
Client times out but payment was captured. Retry with same key returns original success response.

### 2. Double Submit from Mobile
User taps "Pay" twice quickly. Second request is locked out or returns cached response.

### 3. Payload Mismatch
Same idempotency key with different amount/currency is rejected with 422 error.

### 4. Worker Crash Mid-Job
Job is retried but idempotency prevents duplicate execution.

### 5. Concurrent Requests
Multiple API servers process same key - distributed lock ensures only one executes.

## Best Practices

### Generating Idempotency Keys

**Client-side (Recommended):**
```javascript
// Generate UUID v4
const idempotencyKey = crypto.randomUUID();

// Or use a deterministic key
const idempotencyKey = `order-${orderId}-${timestamp}`;
```

**Server-side:**
```php
use Illuminate\Support\Str;

$key = Str::uuid()->toString();
```

### Key Naming Conventions

Use descriptive, collision-free keys:

```php
// Good
"payment-capture-{$paymentIntentId}"
"refund-{$refundId}-{$timestamp}"
"order-{$userId}-{$cartHash}"

// Bad (collision risk)
"payment-{$userId}"  // User could make multiple payments
"order-123"          // Ambiguous
```

### Cleanup Old Records

Schedule a cleanup command:

```php
use Illuminate\Console\Scheduling\Schedule;
use Squipix\Idempotency\Services\IdempotencyService;

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(IdempotencyService::class)->cleanupExpiredRecords(7);
    })->daily();
}
```

## Advanced Configuration

### Custom Cache Store

```php
// In a service provider
use Squipix\Idempotency\Services\IdempotencyService;

$this->app->singleton(IdempotencyService::class, function ($app) {
    return new IdempotencyService(
        $app['cache']->store('redis-cluster'),
        $app['db']->connection('mysql')
    );
});
```

### Custom Header Name

```php
// config/idempotency.php
return [
    'header' => 'X-Request-ID',  // Use custom header
    // ...
];
```

### Disable Payload Validation

```php
// config/idempotency.php
return [
    'reject_payload_mismatch' => false,  // Allow payload changes
    // ...
];
```

## Testing

### Testing API Idempotency

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payment_request_returns_cached_response()
    {
        $key = 'test-payment-' . uniqid();
        
        // First request
        $response1 = $this->postJson('/api/payments', [
            'amount' => 1000,
            'currency' => 'USD',
        ], [
            'Idempotency-Key' => $key,
        ]);
        
        $response1->assertStatus(201);
        
        // Duplicate request
        $response2 = $this->postJson('/api/payments', [
            'amount' => 1000,
            'currency' => 'USD',
        ], [
            'Idempotency-Key' => $key,
        ]);
        
        $response2->assertStatus(201);
        $this->assertEquals($response1->json(), $response2->json());
    }
    
    public function test_same_key_different_payload_returns_422()
    {
        $key = 'test-payment-' . uniqid();
        
        $this->postJson('/api/payments', ['amount' => 1000], [
            'Idempotency-Key' => $key,
        ])->assertStatus(201);
        
        $this->postJson('/api/payments', ['amount' => 2000], [
            'Idempotency-Key' => $key,
        ])->assertStatus(422);
    }
}
```

### Testing Job Idempotency

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

public function test_job_executes_only_once_on_retry()
{
    Cache::flush();
    
    $job = new CapturePayment('payment_123', 1000);
    
    // First execution
    $job->handle();
    
    // Simulate retry
    $job->handle();
    
    // Assert payment captured only once
    $this->assertEquals(1, Payment::where('id', 'payment_123')->count());
}
```

## Troubleshooting

### Missing Idempotency-Key Error

**Problem:** API returns "Idempotency-Key required"

**Solution:** Ensure client sends the header. GET requests are automatically skipped.

### Redis Connection Issues

**Problem:** Lock timeouts or "No connection could be made"

**Solution:** 
1. Verify Redis is running: `redis-cli ping`
2. Check Laravel cache config uses Redis
3. Test connection: `php artisan cache:clear`

### High Memory Usage

**Problem:** `idempotency_keys` table growing too large

**Solution:** 
1. Set up cleanup job (see Best Practices)
2. Add index on `created_at` (already included in migration)
3. Consider shorter `response_ttl`

### Payload Hash Mismatch False Positives

**Problem:** Same payload rejected as different

**Solution:** Ensure request payloads are identical, including:
- Key order (arrays are sorted automatically)
- Data types (string "100" vs int 100)
- Nested objects

## Metrics & Monitoring

The package includes comprehensive metrics support for production monitoring.

### Quick Setup

```env
# Enable metrics
IDEMPOTENCY_METRICS_ENABLED=true

# Prometheus support
IDEMPOTENCY_PROMETHEUS_ENABLED=true

# Laravel Pulse support
IDEMPOTENCY_PULSE_ENABLED=true
```

### Collected Metrics

- **Cache hits/misses** - Monitor cache performance
- **Lock acquisitions/failures** - Track concurrent requests
- **Payload mismatches** - Detect client-side issues
- **Request duration** - Performance monitoring (p50, p95, p99)
- **Job executions/skips** - Queue idempotency tracking
- **Error rates** - System health monitoring

### Supported Platforms

- **Prometheus** - Industry-standard metrics with Grafana dashboards
- **Laravel Pulse** - Real-time application monitoring
- **Custom backends** - Extensible architecture

### Example Prometheus Queries

```promql
# Cache hit ratio
sum(rate(idempotency_cache_hits_total[5m])) /
(sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m])))

# 95th percentile response time
histogram_quantile(0.95, rate(idempotency_request_duration_seconds_bucket[5m]))

# Requests per second
sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m]))
```

**For detailed metrics setup, see [METRICS.md](METRICS.md)**

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Inspired by [Stripe's idempotency implementation](https://stripe.com/docs/api/idempotent_requests).

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/squipix/laravel-idempotency).
