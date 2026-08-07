<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Events\SubscriptionCancelled;
use Illuminate\Support\Facades\Event;
use Stripe\ApiRequestor;

/**
 * Exercises the package end to end through its own registered route — driver
 * resolution (PAYMENT_GATEWAY=stripe → PaymentManager → StripeDriver),
 * signature verification, and event dispatching all in one request, the way
 * a real webhook delivery would actually hit this package.
 */
beforeEach(function () {
    config([
        'laravel-payments.default' => 'stripe',
        'laravel-payments.gateways.stripe.secret' => 'sk_test_fake',
        'laravel-payments.gateways.stripe.webhook_secret' => 'whsec_fake',
    ]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('the package registers a webhook route at the configured path', function () {
    expect(route('laravel-payments.webhook'))->toContain('laravel-payments/webhook');
});

test('a real request to the webhook route resolves the stripe driver, verifies the signature, and dispatches an event', function () {
    Event::fake([SubscriptionCancelled::class]);
    fakeStripeHttp();

    $payload = [
        'id' => 'evt_test',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => 'sub_test',
            'customer' => 'cus_test',
            'status' => 'canceled',
        ]],
    ];
    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_fake');

    $response = test()->call(
        'POST',
        '/laravel-payments/webhook',
        server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
        content: $body,
    );

    $response->assertOk();
    Event::assertDispatched(SubscriptionCancelled::class, fn ($event) => $event->providerSubscriptionId === 'sub_test');
});

test('a request to the webhook route with a bad signature is rejected end to end', function () {
    fakeStripeHttp();

    $payload = ['id' => 'evt_test', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_test']]];
    $body = json_encode($payload);

    $response = test()->call(
        'POST',
        '/laravel-payments/webhook',
        server: ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=not-valid', 'CONTENT_TYPE' => 'application/json'],
        content: $body,
    );

    $response->assertForbidden();
});
