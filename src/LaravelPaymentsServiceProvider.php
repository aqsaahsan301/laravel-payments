<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments;

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaravelPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-payments.php', 'laravel-payments');

        $this->app->singleton(PaymentManager::class);

        $this->app->bind(
            PaymentGateway::class,
            fn (Application $app) => $app->make(PaymentManager::class)->gateway(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/laravel-payments.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-payments.php' => config_path('laravel-payments.php'),
        ], ['laravel-payments', 'laravel-payments-config']);
    }
}
