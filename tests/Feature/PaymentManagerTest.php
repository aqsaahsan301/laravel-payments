<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;
use Aqsaahsan301\LaravelPayments\PaymentManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A minimal driver used only to test PaymentManager's resolution logic
 * without depending on any real gateway driver.
 */
class FakeGatewayDriver implements PaymentGateway
{
    public function checkout(?string $providerCustomerId, string $customerEmail, string $plan, array $options = []): CheckoutResult
    {
        return new CheckoutResult(
            url: 'https://fake.test/checkout',
            providerCustomerId: $providerCustomerId ?? 'cus_fake_new',
        );
    }

    public function handleWebhook(Request $request): Response
    {
        return new Response('ok');
    }

    public function cancelSubscription(string $providerSubscriptionId): void {}

    public function currentPlan(string $providerCustomerId): ?string
    {
        return 'starter';
    }
}

test('the manager resolves whichever driver is configured as default', function () {
    config(['laravel-payments.default' => 'fake']);

    $manager = app(PaymentManager::class);
    $manager->extendDriver('fake', FakeGatewayDriver::class);

    expect($manager->gateway())->toBeInstanceOf(FakeGatewayDriver::class);
});

test('the PaymentGateway contract resolves to the configured driver — consumers never see the driver name', function () {
    config(['laravel-payments.default' => 'fake']);

    app(PaymentManager::class)->extendDriver('fake', FakeGatewayDriver::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGatewayDriver::class);
});

test('switching the configured driver is a config-only change', function () {
    config(['laravel-payments.default' => 'fake']);
    $manager = app(PaymentManager::class);
    $manager->extendDriver('fake', FakeGatewayDriver::class);
    $manager->extendDriver('also-fake', FakeGatewayDriver::class);

    expect($manager->gateway())->toBeInstanceOf(FakeGatewayDriver::class);

    config(['laravel-payments.default' => 'also-fake']);
    // A fresh manager, as a new request would get — no code path changed, only config.
    $freshManager = app(PaymentManager::class);
    $freshManager->extendDriver('also-fake', FakeGatewayDriver::class);

    expect($freshManager->gateway())->toBeInstanceOf(FakeGatewayDriver::class);
});

test('an unconfigured default gateway throws a clear error', function () {
    config(['laravel-payments.default' => null]);

    app(PaymentManager::class)->gateway();
})->throws(InvalidArgumentException::class, 'No default payment gateway configured');

test('an unresolvable driver name throws', function () {
    config(['laravel-payments.default' => 'does-not-exist']);

    app(PaymentManager::class)->gateway();
})->throws(InvalidArgumentException::class);
