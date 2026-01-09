# Installation & Setup Guide

## Prerequisites
- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Redis server (for distributed locking and caching)
- MySQL/PostgreSQL database

## Step 1: Install Package

```bash
composer require squipix/laravel-idempotency
```

## Step 2: Publish Configuration

```bash
# Publish config file
php artisan vendor:publish --tag=idempotency-config

# Publish migrations
php artisan vendor:publish --tag=idempotency-migrations
```

## Step 3: Configure Database

Run the migration:
```bash
php artisan migrate
```

This creates the `idempotency_keys` table.

## Step 4: Configure Redis

Ensure your `.env` has Redis configured:

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Test Redis connection:
```bash
redis-cli ping
# Should return: PONG
```

## Step 5: Configure Package (Optional)

Edit `config/idempotency.php` to customize:

```php
return [
    'header' => 'Idempotency-Key',           // HTTP header name
    'lock_ttl' => 10,                        // Lock timeout (seconds)
    'response_ttl' => 86400,                 // Cache TTL (24 hours)
    'reject_payload_mismatch' => true,       // Reject duplicate keys with different payloads
    'queue' => [
        'enabled' => true,                   // Enable queue idempotency
        'ttl' => 86400,                      // Job cache TTL (24 hours)
    ],
];
```

## Step 6: Apply to Routes

### Single Route
```php
use Illuminate\Support\Facades\Route;

Route::post('/api/payments', [PaymentController::class, 'store'])
    ->middleware(['auth', 'idempotency']);
```

### Route Group
```php
Route::middleware(['auth', 'idempotency'])->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/refunds', [RefundController::class, 'create']);
    Route::post('/transfers', [TransferController::class, 'execute']);
});
```

### Apply Globally (Not Recommended)
```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        // ...
        \squipix\Idempotency\Middleware\IdempotencyMiddleware::class,
    ],
];
```

## Step 7: Protect Queue Jobs

Add to any job that should be idempotent:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use squipix\Idempotency\Jobs\IdempotentJobMiddleware;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $paymentId
    ) {}

    /**
     * Add idempotency middleware
     */
    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }

    /**
     * Define unique key for this job
     */
    public function idempotencyKey(): string
    {
        return "process-payment:{$this->paymentId}";
    }

    public function handle()
    {
        // Your job logic here
        // This will execute only once even if retried
    }
}
```

## Step 8: Schedule Cleanup (Recommended)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up records older than 7 days
    $schedule->command('idempotency:cleanup --days=7')
        ->daily()
        ->at('02:00');
}
```

Or run manually:
```bash
# Clean up records older than 7 days
php artisan idempotency:cleanup --days=7

# Dry run (see what would be deleted)
php artisan idempotency:cleanup --days=7 --dry-run
```

## Step 9: Test the Setup

### Test API Idempotency

```bash
# First request
curl -X POST http://localhost/api/payments \
  -H "Idempotency-Key: test-key-123" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"amount": 1000, "currency": "USD"}'

# Duplicate request (should return same response)
curl -X POST http://localhost/api/payments \
  -H "Idempotency-Key: test-key-123" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"amount": 1000, "currency": "USD"}'
```

### Test Job Idempotency

```php
// In tinker or a test
php artisan tinker

>>> $job = new \App\Jobs\ProcessPayment('payment_123');
>>> dispatch($job);
>>> dispatch($job); // Second dispatch won't execute
```

## Troubleshooting

### Error: "Idempotency-Key required"
**Cause**: Client didn't send the header
**Solution**: Add header to request or exclude route from middleware

### Error: "Request in progress" (409)
**Cause**: Concurrent requests with same key
**Solution**: This is expected behavior. Client should wait and retry.

### Error: "Payload mismatch for idempotency key" (422)
**Cause**: Same key used with different payload
**Solution**: 
- Use different key for different requests
- Or disable validation: `'reject_payload_mismatch' => false`

### Redis Connection Failed
**Solution**: 
```bash
# Check Redis is running
redis-cli ping

# Check Laravel can connect
php artisan cache:clear

# Verify config
php artisan config:cache
```

### Database Too Large
**Solution**:
```bash
# Run cleanup more frequently
php artisan idempotency:cleanup --days=1

# Or reduce TTL in config
'response_ttl' => 3600, // 1 hour instead of 24
```

## Performance Optimization

### For High Traffic

1. **Use Redis Cluster**:
```php
// config/database.php
'redis' => [
    'client' => 'phpredis',
    'clusters' => [
        'default' => [
            ['host' => '127.0.0.1', 'port' => 6379],
            ['host' => '127.0.0.1', 'port' => 6380],
        ],
    ],
],
```

2. **Use Compression**:
```php
// config/database.php
'redis' => [
    'options' => [
        'compression' => Redis::COMPRESSION_LZ4,
        'serializer' => Redis::SERIALIZER_IGBINARY,
    ],
],
```

3. **Index Database**:
```sql
-- Already included in migration, but verify:
SHOW INDEX FROM idempotency_keys;
```

4. **Use Read Replicas** (for very high traffic):
```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(IdempotencyService::class, function ($app) {
    return new IdempotencyService(
        $app['cache']->store('redis'),
        $app['db']->connection('mysql-read-replica')
    );
});
```

## Production Checklist

- [ ] Redis is configured and running
- [ ] Migrations are run
- [ ] Config is published and customized
- [ ] Cleanup command is scheduled
- [ ] Middleware is applied to sensitive routes
- [ ] Job middleware is added to critical jobs
- [ ] Monitoring is set up for failed jobs
- [ ] Logs are configured for idempotency errors
- [ ] Database indexes are verified
- [ ] Redis memory limit is appropriate
- [ ] Backup strategy includes idempotency_keys table

## Support

For issues, please check:
1. Laravel logs: `storage/logs/laravel.log`
2. Redis logs: `redis-cli monitor`
3. Database queries: Enable query log in Laravel
4. GitHub issues: https://github.com/squipix/laravel-idempotency/issues

## Next Steps

- Read the [README.md](README.md) for detailed usage examples
- Check [examples/Usage.php](examples/Usage.php) for real-world scenarios
- Review [PACKAGE_SUMMARY.md](PACKAGE_SUMMARY.md) for architecture overview
