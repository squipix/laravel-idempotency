<?php

namespace Squipix\Idempotency;

use Illuminate\Support\ServiceProvider;
use Squipix\Idempotency\Services\IdempotencyService;
use Squipix\Idempotency\Middleware\IdempotencyMiddleware;
use Squipix\Idempotency\Console\CleanupExpiredKeysCommand;
use Squipix\Idempotency\Metrics\MetricsCollector;

class IdempotencyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/idempotency.php', 'idempotency');

        // Register the idempotency service as singleton
        $this->app->singleton(IdempotencyService::class, function ($app) {
            return new IdempotencyService(
                $app['cache']->store(),
                $app['db']->connection()
            );
        });

        // Register metrics collector as singleton
        $this->app->singleton(MetricsCollector::class, function ($app) {
            return new MetricsCollector();
        });
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/idempotency.php' => config_path('idempotency.php'),
        ], 'idempotency-config');

        // Publish migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_idempotency_keys_table.php' => database_path('migrations/' . date('Y_m_d_His') . '_create_idempotency_keys_table.php'),
            ], 'idempotency-migrations');

            // Register commands
            $this->commands([
                CleanupExpiredKeysCommand::class,
            ]);
        }

        // Register middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('idempotency', IdempotencyMiddleware::class);
    }
}
