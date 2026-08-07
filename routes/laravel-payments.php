<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Route;

// The gateway is provider-driven throughout — even the webhook route never
// names Stripe. Whichever driver PAYMENT_GATEWAY points at handles this.
//
// The provider calling this (Stripe, etc.) sends it unauthenticated and
// can't supply a CSRF token; exclude this URI from CSRF verification in the
// host app's bootstrap/app.php. Each driver verifies its own request
// signature instead (see StripeDriver::handleWebhook()).
Route::post(
    config('laravel-payments.webhook_path', 'laravel-payments/webhook'),
    fn () => app(PaymentGateway::class)->handleWebhook(request()),
)->name('laravel-payments.webhook');
