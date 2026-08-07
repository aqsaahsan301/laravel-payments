<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Drivers\StripeDriver;
use Stripe\ApiRequestor;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

test('charge creates a one-time payment checkout session, not a subscription', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['id' => 'cus_new', 'object' => 'customer']);
    $http->queue(200, ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/cs_test']);

    $result = app(StripeDriver::class)->charge(
        customerEmail: 'ayesha@example.com',
        amount: 4900,
        currency: 'usd',
        options: ['success_url' => 'https://app.test/paid?ok', 'cancel_url' => 'https://app.test/paid?cancelled'],
    );

    expect($result->url)->toBe('https://checkout.stripe.com/cs_test')
        ->and($result->providerCustomerId)->toBe('cus_new')
        ->and($http->requests[1]['params']['mode'])->toBe('payment')
        ->and($http->requests[1]['params']['line_items'][0]['price_data']['unit_amount'])->toBe(4900)
        ->and($http->requests[1]['params']['line_items'][0]['price_data']['currency'])->toBe('usd');
});

test('charge reuses an existing customer id when given one', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/cs_test']);

    $result = app(StripeDriver::class)->charge(
        customerEmail: 'ayesha@example.com',
        amount: 1900,
        currency: 'usd',
        options: [
            'success_url' => 'https://app.test/paid?ok',
            'cancel_url' => 'https://app.test/paid?cancelled',
            'provider_customer_id' => 'cus_existing',
        ],
    );

    expect($result->providerCustomerId)->toBe('cus_existing')
        ->and($http->requests)->toHaveCount(1);
});

test('charge requires success_url and cancel_url', function () {
    fakeStripeHttp();

    app(StripeDriver::class)->charge(
        customerEmail: 'ayesha@example.com',
        amount: 1900,
        currency: 'usd',
        options: [],
    );
})->throws(InvalidArgumentException::class, "The 'success_url' option is required");

test('checkout creates a new customer when none is given, then a checkout session', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['id' => 'cus_new', 'object' => 'customer']);
    $http->queue(200, ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/cs_test']);

    $result = app(StripeDriver::class)->checkout(
        providerCustomerId: null,
        customerEmail: 'ayesha@example.com',
        plan: 'starter',
        options: ['success_url' => 'https://app.test/billing?ok', 'cancel_url' => 'https://app.test/billing?cancelled'],
    );

    expect($result->url)->toBe('https://checkout.stripe.com/cs_test')
        ->and($result->providerCustomerId)->toBe('cus_new')
        ->and($http->requests)->toHaveCount(2)
        ->and($http->requests[0]['url'])->toContain('/customers')
        ->and($http->requests[1]['url'])->toContain('/checkout/sessions');
});

test('checkout reuses an existing customer id instead of creating one', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/cs_test']);

    $result = app(StripeDriver::class)->checkout(
        providerCustomerId: 'cus_existing',
        customerEmail: 'ayesha@example.com',
        plan: 'starter',
        options: ['success_url' => 'https://app.test/billing?ok', 'cancel_url' => 'https://app.test/billing?cancelled'],
    );

    expect($result->providerCustomerId)->toBe('cus_existing')
        ->and($http->requests)->toHaveCount(1);
});

test('checkout rejects an unconfigured plan', function () {
    fakeStripeHttp();

    app(StripeDriver::class)->checkout(
        providerCustomerId: 'cus_existing',
        customerEmail: 'ayesha@example.com',
        plan: 'nonexistent',
        options: ['success_url' => 'https://app.test/ok', 'cancel_url' => 'https://app.test/cancel'],
    );
})->throws(InvalidArgumentException::class, 'Unknown plan [nonexistent]');

test('checkout requires success_url and cancel_url', function () {
    fakeStripeHttp();

    app(StripeDriver::class)->checkout(
        providerCustomerId: 'cus_existing',
        customerEmail: 'ayesha@example.com',
        plan: 'starter',
        options: [],
    );
})->throws(InvalidArgumentException::class, "The 'success_url' option is required");

test('a validly signed webhook is accepted', function () {
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['id' => 'sub_test']],
    ], 'whsec_fake');

    $response = app(StripeDriver::class)->handleWebhook($request);

    expect($response->getStatusCode())->toBe(200);
});

test('a webhook with an invalid signature is rejected', function () {
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['id' => 'sub_test']],
    ], 'wrong_secret');

    app(StripeDriver::class)->handleWebhook($request);
})->throws(AccessDeniedHttpException::class);

test('a webhook is rejected when no webhook secret is configured', function () {
    config(['laravel-payments.gateways.stripe.webhook_secret' => null]);
    fakeStripeHttp();

    $request = signedStripeWebhookRequest([
        'id' => 'evt_test',
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['id' => 'sub_test']],
    ], 'whsec_fake');

    app(StripeDriver::class)->handleWebhook($request);
})->throws(InvalidArgumentException::class, 'STRIPE_WEBHOOK_SECRET is not configured');

test('cancelSubscription calls Stripe\'s cancel endpoint for that subscription', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['id' => 'sub_test', 'object' => 'subscription', 'status' => 'canceled']);

    app(StripeDriver::class)->cancelSubscription('sub_test');

    expect($http->requests)->toHaveCount(1)
        ->and($http->requests[0]['method'])->toBe('delete')
        ->and($http->requests[0]['url'])->toContain('/subscriptions/sub_test');
});

test('currentPlan maps the active subscription\'s price back to a plan slug', function () {
    $http = fakeStripeHttp();
    $http->queue(200, [
        'object' => 'list',
        'data' => [[
            'id' => 'sub_test',
            'object' => 'subscription',
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_test',
                    'object' => 'subscription_item',
                    'price' => ['id' => 'price_starter', 'object' => 'price'],
                ]],
            ],
        ]],
    ]);

    $plan = app(StripeDriver::class)->currentPlan('cus_existing');

    expect($plan)->toBe('starter');
});

test('currentPlan returns null when there is no active subscription', function () {
    $http = fakeStripeHttp();
    $http->queue(200, ['object' => 'list', 'data' => []]);

    $plan = app(StripeDriver::class)->currentPlan('cus_existing');

    expect($plan)->toBeNull();
});
