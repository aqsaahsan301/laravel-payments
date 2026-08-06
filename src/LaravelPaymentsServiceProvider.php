<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments;

use Illuminate\Support\ServiceProvider;

class LaravelPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-payments.php', 'laravel-payments');

        $this->app->singleton(LaravelPayments::class);
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
