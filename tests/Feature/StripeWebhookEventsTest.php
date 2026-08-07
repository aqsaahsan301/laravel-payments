<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Drivers\StripeDriver;
use Aqsaahsan301\LaravelPayments\Events\PaymentFailed;
use Aqsaahsan301\LaravelPayments\Events\PaymentSucceeded;
use Aqsaahsan301\LaravelPayments\Events\SubscriptionCancelled;
use Aqsaahsan301\LaravelPayments\Events\SubscriptionUpdated;
use Illuminate\Support\Facades\Event;
use Stripe\ApiRequestor;

beforeEach(function () {
    config([
        'laravel-payments.gateways.stripe.secret' => 'sk_test_fake',
        'laravel-payments.gateways.stripe.webhook_secret' => 'whsec_fake',
        'laravel-payments.plans.starter.stripe' => 'price_starter',
    ]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('invoice.payment_succeeded dispatches the package\'s own PaymentSucceeded event, not a Stripe one', function () {
    Event::fake([PaymentSucceeded::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'customer' => 'cus_test',
            'subscription' => 'sub_test',
            'amount_paid' => 1900,
            'currency' => 'usd',
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertDispatched(PaymentSucceeded::class, fn ($event) => $event->providerCustomerId === 'cus_test'
        && $event->providerSubscriptionId === 'sub_test'
        && $event->amount === 1900
        && $event->currency === 'usd',
    );
});

test('checkout.session.completed in payment mode dispatches PaymentSucceeded (one-time charge)', function () {
    Event::fake([PaymentSucceeded::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'mode' => 'payment',
            'customer' => 'cus_test',
            'amount_total' => 4900,
            'currency' => 'usd',
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertDispatched(PaymentSucceeded::class, fn ($event) => $event->providerCustomerId === 'cus_test'
        && $event->providerSubscriptionId === null
        && $event->amount === 4900,
    );
});

test('checkout.session.completed in subscription mode dispatches nothing (invoice.payment_succeeded handles that instead)', function () {
    Event::fake([PaymentSucceeded::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'mode' => 'subscription',
            'customer' => 'cus_test',
            'amount_total' => 1900,
            'currency' => 'usd',
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

test('invoice.payment_failed dispatches PaymentFailed', function () {
    Event::fake([PaymentFailed::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'customer' => 'cus_test',
            'subscription' => 'sub_test',
            'amount_due' => 4900,
            'currency' => 'usd',
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertDispatched(PaymentFailed::class, fn ($event) => $event->providerCustomerId === 'cus_test'
        && $event->amount === 4900,
    );
});

test('customer.subscription.deleted dispatches SubscriptionCancelled', function () {
    Event::fake([SubscriptionCancelled::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => 'sub_test',
            'customer' => 'cus_test',
            'status' => 'canceled',
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertDispatched(SubscriptionCancelled::class, fn ($event) => $event->providerCustomerId === 'cus_test'
        && $event->providerSubscriptionId === 'sub_test',
    );
});

test('customer.subscription.updated dispatches SubscriptionUpdated with the new status and price', function () {
    Event::fake([SubscriptionUpdated::class]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_test',
            'customer' => 'cus_test',
            'status' => 'past_due',
            'items' => ['data' => [[
                'price' => ['id' => 'price_starter'],
            ]]],
        ]],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);

    Event::assertDispatched(SubscriptionUpdated::class, fn ($event) => $event->providerCustomerId === 'cus_test'
        && $event->providerSubscriptionId === 'sub_test'
        && $event->status === 'past_due'
        && $event->providerPriceId === 'price_starter',
    );
});

test('an unrecognized event type dispatches nothing and still returns 200', function () {
    Event::fake();
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'charge.dispute.created',
        'data' => ['object' => ['id' => 'dp_test']],
    ], 'whsec_fake');

    $response = app(StripeDriver::class)->handleWebhook($request);

    expect($response->getStatusCode())->toBe(200);
    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(PaymentFailed::class);
    Event::assertNotDispatched(SubscriptionCancelled::class);
    Event::assertNotDispatched(SubscriptionUpdated::class);
});
