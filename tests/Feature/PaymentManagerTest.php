<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Aqsaahsan301\LaravelPayments\Contracts\SupportsSubscriptions;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\ChargeResult;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;
use Aqsaahsan301\LaravelPayments\PaymentManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A minimal driver used only to test PaymentManager's resolution logic
 * without depending on any real gateway driver. Implements only the base
 * contract — a one-time/invoice-only gateway (FPX, ToyyibPay, ...) with no
 * subscription concept.
 */
class FakeGatewayDriver implements PaymentGateway
{
    public function charge(string $customerEmail, int $amount, string $currency, array $options = []): ChargeResult
    {
        return new ChargeResult(url: 'https://fake.test/charge', providerCustomerId: 'cus_fake_new');
    }

    public function handleWebhook(Request $request): Response
    {
        return new Response('ok');
    }
}

/**
 * A fake for a gateway that DOES support subscriptions, to prove the
 * SupportsSubscriptions binding resolves for drivers that implement it.
 */
class FakeSubscriptionGatewayDriver implements PaymentGateway, SupportsSubscriptions
{
    public function charge(string $customerEmail, int $amount, string $currency, array $options = []): ChargeResult
    {
        return new ChargeResult(url: 'https://fake.test/charge', providerCustomerId: 'cus_fake_new');
    }

    public function handleWebhook(Request $request): Response
    {
        return new Response('ok');
    }

    public function checkout(?string $providerCustomerId, string $customerEmail, string $plan, array $options = []): CheckoutResult
    {
        return new CheckoutResult(url: 'https://fake.test/checkout', providerCustomerId: $providerCustomerId ?? 'cus_fake_new');
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

test('SupportsSubscriptions resolves when the configured driver implements it', function () {
    config(['laravel-payments.default' => 'sub-fake']);
    app(PaymentManager::class)->extendDriver('sub-fake', FakeSubscriptionGatewayDriver::class);

    expect(app(SupportsSubscriptions::class))->toBeInstanceOf(FakeSubscriptionGatewayDriver::class);
});

test('resolving SupportsSubscriptions throws a clear error when the configured driver does not support it', function () {
    config(['laravel-payments.default' => 'fake']);
    app(PaymentManager::class)->extendDriver('fake', FakeGatewayDriver::class);

    app(SupportsSubscriptions::class);
})->throws(RuntimeException::class, 'does not support subscriptions');
