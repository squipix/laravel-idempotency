# Metrics & Monitoring Guide

The Laravel Idempotency package includes comprehensive metrics support for monitoring performance and behavior in production environments.

## Supported Platforms

- **Prometheus** - Industry-standard metrics collection
- **Laravel Pulse** - Laravel's native real-time application monitoring
- **Custom backends** - Extensible architecture

## Quick Start

### Enable Metrics

```env
# .env
IDEMPOTENCY_METRICS_ENABLED=true

# For Prometheus
IDEMPOTENCY_PROMETHEUS_ENABLED=true
IDEMPOTENCY_PROMETHEUS_NAMESPACE=myapp

# For Laravel Pulse
IDEMPOTENCY_PULSE_ENABLED=true
```

### Configuration

```php
// config/idempotency.php
'metrics' => [
    'enabled' => env('IDEMPOTENCY_METRICS_ENABLED', false),
    
    'prometheus' => [
        'enabled' => env('IDEMPOTENCY_PROMETHEUS_ENABLED', false),
        'namespace' => env('IDEMPOTENCY_PROMETHEUS_NAMESPACE', 'app'),
        'adapter' => 'prometheus',
        'buckets' => [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5, 10],
    ],
    
    'pulse' => [
        'enabled' => env('IDEMPOTENCY_PULSE_ENABLED', false),
    ],
],
```

## Collected Metrics

### API Metrics

#### `idempotency_cache_hits_total`
**Type**: Counter  
**Labels**: `type` (redis, database)  
**Description**: Number of successful cache hits

```promql
# Prometheus query example
rate(idempotency_cache_hits_total[5m])
```

#### `idempotency_cache_misses_total`
**Type**: Counter  
**Description**: Number of cache misses (new requests)

#### `idempotency_locks_acquired_total`
**Type**: Counter  
**Description**: Number of successful lock acquisitions

#### `idempotency_locks_failed_total`
**Type**: Counter  
**Description**: Number of failed lock acquisitions (concurrent requests)

#### `idempotency_payload_mismatches_total`
**Type**: Counter  
**Description**: Number of payload mismatch rejections (422 errors)

#### `idempotency_request_duration_seconds`
**Type**: Histogram  
**Labels**: `status` (cache_hit, success, error)  
**Description**: Request processing duration

**Percentiles**:
- p50, p90, p95, p99
- Useful for SLO tracking

```promql
# 95th percentile response time
histogram_quantile(0.95, 
  rate(idempotency_request_duration_seconds_bucket[5m])
)
```

### Queue Metrics

#### `idempotency_jobs_executed_total`
**Type**: Counter  
**Labels**: `status` (success, failed)  
**Description**: Number of jobs executed

#### `idempotency_jobs_skipped_total`
**Type**: Counter  
**Description**: Number of jobs skipped due to idempotency

### Database Metrics

#### `idempotency_database_query_duration_seconds`
**Type**: Histogram  
**Labels**: `operation` (select, insert)  
**Description**: Database query duration

### Error Metrics

#### `idempotency_errors_total`
**Type**: Counter  
**Labels**: `type` (middleware_exception, job_failed)  
**Description**: Number of errors encountered

---

## Prometheus Setup

### 1. Install Prometheus Client

```bash
composer require promphp/prometheus_client_php
```

### 2. Configure Adapter

```php
// app/Providers/AppServiceProvider.php
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

public function register()
{
    $this->app->singleton('prometheus', function ($app) {
        Redis::setDefaultOptions([
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'timeout' => 0.1,
        ]);
        
        return new CollectorRegistry(new Redis());
    });
}
```

### 3. Expose Metrics Endpoint

```php
// routes/web.php
use Prometheus\RenderTextFormat;

Route::get('/metrics', function () {
    $registry = app('prometheus');
    $renderer = new RenderTextFormat();
    
    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
})->middleware('auth.basic');
```

### 4. Configure Prometheus Server

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'laravel-idempotency'
    scrape_interval: 15s
    static_configs:
      - targets: ['your-app.com']
    metrics_path: '/metrics'
    basic_auth:
      username: 'prometheus'
      password: 'your-password'
```

---

## Laravel Pulse Setup

### 1. Install Laravel Pulse

```bash
composer require laravel/pulse
php artisan vendor:publish --tag=pulse-config
php artisan migrate
```

### 2. Enable in Config

```php
// config/idempotency.php
'metrics' => [
    'enabled' => true,
    'pulse' => [
        'enabled' => true,
    ],
],
```

### 3. View Dashboard

Visit `/pulse` to see real-time metrics.

### 4. Custom Pulse Recorder (Optional)

```php
// app/Pulse/Recorders/IdempotencyRecorder.php
namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;

class IdempotencyRecorder extends Recorder
{
    public function record(): void
    {
        $this->pulse->set(
            type: 'idempotency_cache_hits',
            key: 'redis',
            value: $this->getMetricsValue('cache_hits')
        );
    }
}
```

---

## Monitoring Best Practices

### 1. Cache Hit Ratio

Monitor the cache hit ratio to ensure Redis is working:

```promql
# Prometheus
sum(rate(idempotency_cache_hits_total[5m])) /
(sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m])))
```

**Target**: > 80% hit ratio for optimal performance

### 2. Lock Contention

Monitor failed lock acquisitions:

```promql
# High lock failures indicate concurrent duplicate requests
rate(idempotency_locks_failed_total[5m])
```

**Action**: If high (>5%), investigate client retry logic

### 3. Payload Mismatches

Track payload mismatch rate:

```promql
rate(idempotency_payload_mismatches_total[5m])
```

**Action**: High rate indicates client bugs or key reuse

### 4. Response Time

Monitor p95 response time:

```promql
histogram_quantile(0.95, 
  rate(idempotency_request_duration_seconds_bucket[5m])
)
```

**Target**:
- Cache hits: < 10ms
- Database replay: < 50ms
- New requests: < 500ms

### 5. Job Skips

Monitor skipped jobs:

```promql
rate(idempotency_jobs_skipped_total[5m])
```

**Insight**: High rate normal during retry storms

---

## Alerting Rules

### Prometheus Alerts

```yaml
# alerts/idempotency.yml
groups:
  - name: idempotency
    rules:
      # Low cache hit ratio
      - alert: IdempotencyLowCacheHitRatio
        expr: |
          sum(rate(idempotency_cache_hits_total[5m])) /
          (sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m]))) < 0.7
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Low idempotency cache hit ratio"
          description: "Cache hit ratio is {{ $value | humanizePercentage }}"
      
      # High lock failures
      - alert: IdempotencyHighLockFailures
        expr: rate(idempotency_locks_failed_total[5m]) > 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High idempotency lock failure rate"
      
      # High payload mismatches
      - alert: IdempotencyPayloadMismatches
        expr: rate(idempotency_payload_mismatches_total[5m]) > 1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Idempotency payload mismatches detected"
      
      # Slow response times
      - alert: IdempotencySlowResponses
        expr: |
          histogram_quantile(0.95, 
            rate(idempotency_request_duration_seconds_bucket[5m])
          ) > 1
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Slow idempotency responses"
      
      # High error rate
      - alert: IdempotencyErrors
        expr: rate(idempotency_errors_total[5m]) > 0.1
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Idempotency errors detected"
```

---

## Grafana Dashboard

### Example Dashboard JSON

```json
{
  "dashboard": {
    "title": "Laravel Idempotency",
    "panels": [
      {
        "title": "Cache Hit Ratio",
        "targets": [
          {
            "expr": "sum(rate(idempotency_cache_hits_total[5m])) / (sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m])))"
          }
        ],
        "type": "gauge"
      },
      {
        "title": "Request Duration (p95)",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(idempotency_request_duration_seconds_bucket[5m]))",
            "legendFormat": "p95"
          }
        ],
        "type": "graph"
      },
      {
        "title": "Requests per Second",
        "targets": [
          {
            "expr": "sum(rate(idempotency_cache_hits_total[5m])) + sum(rate(idempotency_cache_misses_total[5m]))",
            "legendFormat": "Total"
          },
          {
            "expr": "sum(rate(idempotency_cache_hits_total{type=\"redis\"}[5m]))",
            "legendFormat": "Cache Hits"
          }
        ],
        "type": "graph"
      },
      {
        "title": "Lock Failures",
        "targets": [
          {
            "expr": "rate(idempotency_locks_failed_total[5m])"
          }
        ],
        "type": "graph"
      }
    ]
  }
}
```

Import via: **Dashboard > Import > Paste JSON**

---

## Performance Impact

Metrics collection has **minimal overhead**:

- **Cache hits**: < 0.1ms additional latency
- **New requests**: < 0.5ms additional latency
- **Memory**: ~1-2MB per 10,000 metrics

### Disabling in Specific Environments

```php
// config/idempotency.php
'metrics' => [
    'enabled' => env('APP_ENV') === 'production',
],
```

---

## Custom Metrics

### Extend MetricsCollector

```php
namespace App\Metrics;

use squipix\Idempotency\Metrics\MetricsCollector as BaseCollector;

class CustomMetricsCollector extends BaseCollector
{
    public function recordCustomMetric(string $name, $value): void
    {
        $this->recordToPrometheus($name, $value);
        $this->recordToPulse($name, $value);
    }
}
```

### Register Custom Collector

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(MetricsCollector::class, function ($app) {
    return new CustomMetricsCollector();
});
```

---

## Debugging Metrics

### Check if Metrics are Enabled

```php
use squipix\Idempotency\Metrics\MetricsCollector;

$metrics = app(MetricsCollector::class);
$summary = $metrics->getMetricsSummary();

dd($summary);
```

### Test Metrics in Tinker

```php
php artisan tinker

>>> $metrics = app(\squipix\Idempotency\Metrics\MetricsCollector::class);
>>> $metrics->incrementCacheHit('redis');
>>> $metrics->getMetricsSummary();
```

### View Prometheus Metrics

```bash
curl http://your-app.com/metrics
```

---

## Troubleshooting

### Metrics Not Appearing

1. **Check config**:
   ```php
   config('idempotency.metrics.enabled') // Should be true
   ```

2. **Verify Prometheus adapter**:
   ```php
   app('prometheus') // Should not throw exception
   ```

3. **Check Redis connection**:
   ```bash
   redis-cli ping
   ```

### High Memory Usage

Prometheus stores metrics in Redis. Clean up old metrics:

```php
use Prometheus\Storage\Redis;

Redis::wipeStorage();
```

### Pulse Not Recording

1. Ensure Pulse is installed and migrated
2. Check pulse configuration
3. Verify pulse worker is running:
   ```bash
   php artisan pulse:check
   ```

---

## Production Checklist

- [ ] Metrics enabled in config
- [ ] Prometheus scraping configured
- [ ] Alerting rules deployed
- [ ] Grafana dashboard imported
- [ ] Alert notifications configured (PagerDuty, Slack)
- [ ] Metrics retention configured (30-90 days)
- [ ] Redis storage for Prometheus configured
- [ ] Dashboard access permissions set
- [ ] Metrics documented for team

---

## Additional Resources

- [Prometheus Documentation](https://prometheus.io/docs/)
- [Laravel Pulse Documentation](https://laravel.com/docs/pulse)
- [Grafana Dashboards](https://grafana.com/docs/)
- [PromQL Guide](https://prometheus.io/docs/prometheus/latest/querying/basics/)

---

**Metrics are essential for production reliability. Enable them to gain visibility into your idempotency layer!** 📊
