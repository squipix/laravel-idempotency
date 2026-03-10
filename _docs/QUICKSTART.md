# Quick Start Guide

Get up and running with Laravel Idempotency in 5 minutes.

## Installation

```bash
# Install package
composer require squipix/laravel-idempotency

# Publish assets
php artisan vendor:publish --tag=idempotency-config
php artisan vendor:publish --tag=idempotency-migrations

# Run migration
php artisan migrate
```

## Basic Usage

### 1. Protect an API Route

```php
// routes/api.php
Route::post('/payments', [PaymentController::class, 'create'])
    ->middleware('idempotency');
```

### 2. Make a Request

```bash
curl -X POST https://your-app.com/api/payments \
  -H "Idempotency-Key: unique-key-123" \
  -H "Content-Type: application/json" \
  -d '{"amount": 1000, "currency": "USD"}'
```

**That's it!** Duplicate requests will now return the cached response.

## Queue Protection

### 1. Add Middleware to Job

```php
use Squipix\Idempotency\Jobs\IdempotentJobMiddleware;

class ProcessPayment implements ShouldQueue
{
    public function __construct(public string $paymentId) {}

    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }

    public function idempotencyKey(): string
    {
        return "process-payment:{$this->paymentId}";
    }

    public function handle()
    {
        // Your job logic - will execute only once
    }
}
```

**Done!** Your job is now protected from duplicate execution.

## Cleanup Old Records

Schedule in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('idempotency:cleanup')->daily();
}
```

## Configuration (Optional)

Edit `config/idempotency.php`:

```php
return [
    'header' => 'Idempotency-Key',  // Custom header name
    'lock_ttl' => 10,                // Lock timeout in seconds
    'response_ttl' => 86400,         // Cache for 24 hours
    'reject_payload_mismatch' => true, // Reject different payloads
];
```

## Common Patterns

### Payment Endpoint

```php
Route::post('/api/payments', function (Request $request) {
    $validated = $request->validate([
        'amount' => 'required|integer',
        'currency' => 'required|string',
    ]);

    $payment = Payment::create($validated);
    
    return response()->json($payment, 201);
})->middleware('idempotency');
```

### Job with Retry Protection

```php
class CapturePayment implements ShouldQueue
{
    public $tries = 3;

    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }

    public function idempotencyKey(): string
    {
        return "capture:{$this->paymentId}";
    }
}
```

## Testing

```php
public function test_duplicate_request_returns_same_response()
{
    $key = 'test-' . uniqid();
    
    $response1 = $this->postJson('/api/payments', 
        ['amount' => 1000],
        ['Idempotency-Key' => $key]
    );
    
    $response2 = $this->postJson('/api/payments', 
        ['amount' => 1000],
        ['Idempotency-Key' => $key]
    );
    
    $this->assertEquals(
        $response1->json('id'),
        $response2->json('id')
    );
}
```

## Troubleshooting

### Missing Header Error?
Add the header: `Idempotency-Key: your-unique-key`

### "Request in progress" (409)?
Wait a moment - another request with same key is processing

### "Payload mismatch" (422)?
Same key used with different data. Use a new key or disable validation.

## Learn More

- **Full Documentation**: See [README.md](README.md)
- **Installation Guide**: See [INSTALLATION.md](INSTALLATION.md)
- **Examples**: See [examples/Usage.php](examples/Usage.php)

---

**That's all you need to get started!** 🚀

For production deployment, read the [full installation guide](INSTALLATION.md).
