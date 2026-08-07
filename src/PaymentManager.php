<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments;

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Aqsaahsan301\LaravelPayments\Drivers\StripeDriver;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves the configured gateway driver, the same pattern Laravel itself
 * uses for MailManager/QueueManager. Host apps set PAYMENT_GATEWAY=stripe
 * (or whatever driver name) in .env; nothing else in their code changes when
 * that value changes, because everything depends on PaymentGateway, not on
 * PaymentManager or any concrete driver.
 *
 * @method \Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult checkout(?string $providerCustomerId, string $customerEmail, string $plan, array<string, mixed> $options = [])
 * @method \Symfony\Component\HttpFoundation\Response handleWebhook(\Illuminate\Http\Request $request)
 * @method void cancelSubscription(string $providerSubscriptionId)
 * @method string|null currentPlan(string $providerCustomerId)
 */
class PaymentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('laravel-payments.default');

        if (! $driver) {
            throw new InvalidArgumentException(
                'No default payment gateway configured. Set PAYMENT_GATEWAY in your .env or laravel-payments.default in config.',
            );
        }

        return $driver;
    }

    /**
     * The built-in Stripe driver, resolved via the container the same way
     * Laravel's own Manager subclasses resolve createXDriver() methods.
     */
    protected function createStripeDriver(): StripeDriver
    {
        return $this->container->make(StripeDriver::class);
    }

    /**
     * Register a driver by name, resolved via the container so drivers can
     * type-hint their own dependencies normally. For third-party drivers
     * (Billplz, ToyyibPay, ...) shipped outside this package.
     */
    public function extendDriver(string $driver, string $concreteClass): static
    {
        return $this->extend($driver, fn () => $this->container->make($concreteClass));
    }

    /**
     * Resolve the active driver as the PaymentGateway contract, for consumers
     * that don't need PaymentManager's own extend()/driver() surface.
     */
    public function gateway(?string $driver = null): PaymentGateway
    {
        return $this->driver($driver);
    }
}
