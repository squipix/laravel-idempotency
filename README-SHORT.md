# Laravel Idempotency - Quick Reference

## Installation

```bash
composer require squipix/laravel-idempotency
php artisan vendor:publish --tag=idempotency-config
php artisan vendor:publish --tag=idempotency-migrations
php artisan migrate
```

## Basic Usage

### API Routes

```php
Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware('idempotency');
```

Client request:
```bash
curl -X POST https://api.example.com/payments \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -H "Content-Type: application/json" \
  -d '{"amount": 1000, "currency": "USD"}'
```

### Queue Jobs

```php
use Squipix\Idempotency\Jobs\IdempotentJobMiddleware;

class ProcessPayment implements ShouldQueue
{
    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }
}
```

## Configuration

```php
// config/idempotency.php
return [
    'enabled' => true,
    'header_name' => 'Idempotency-Key',
    'response_ttl' => 86400, // 24 hours
    'lock_ttl' => 300, // 5 minutes
];
```

## Features

✅ Stripe-style API guarantees
✅ Horizontal scaling with Redis
✅ Queue job deduplication
✅ Payload validation
✅ Metrics support (Prometheus/Pulse)
✅ OpenAPI documentation

## Documentation

- 📖 [Full Documentation](README.md)
- 🚀 [Quick Start](QUICKSTART.md)
- 📦 [Installation Guide](INSTALLATION.md)
- 📊 [Metrics](METRICS.md)
- 🔌 [OpenAPI](OPENAPI.md)

## Support

- GitHub: [Issues](https://github.com/squipix/laravel-idempotency/issues)
- Email: support@squipix.com

## License

MIT License - see [LICENSE](LICENSE) file.
