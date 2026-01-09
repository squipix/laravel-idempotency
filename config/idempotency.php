<?php
return [
    'header' => 'Idempotency-Key',
    'lock_ttl' => 10, // seconds
    'response_ttl' => 86400, // 24 hours
    'reject_payload_mismatch' => true,
    'queue' => [
        'enabled' => true,
        'ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    |
    | Enable metrics collection for monitoring idempotency performance.
    | Supports both Prometheus and Laravel Pulse.
    |
    */
    'metrics' => [
        'enabled' => env('IDEMPOTENCY_METRICS_ENABLED', false),

        // Prometheus configuration
        'prometheus' => [
            'enabled' => env('IDEMPOTENCY_PROMETHEUS_ENABLED', false),
            'namespace' => env('IDEMPOTENCY_PROMETHEUS_NAMESPACE', 'app'),
            'adapter' => 'prometheus', // Service container binding for CollectorRegistry
            'buckets' => [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5, 10], // Histogram buckets (seconds)
        ],

        // Laravel Pulse configuration
        'pulse' => [
            'enabled' => env('IDEMPOTENCY_PULSE_ENABLED', false),
        ],
    ],
];
