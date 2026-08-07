<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Contracts;

use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;

/**
 * Optional capability for drivers whose gateway actually supports recurring
 * billing (Stripe today). Host apps that want subscription features resolve
 * this from the container — or check `$gateway instanceof SupportsSubscriptions`
 * on whatever PaymentGateway they already have — rather than assuming every
 * driver can do it, since plenty of real gateways (FPX, Billplz, ToyyibPay,
 * DuitNow QR) are one-time/invoice-only with no subscription concept.
 */
interface SupportsSubscriptions
{
    /**
     * Start a checkout for a plan.
     *
     * @param  string|null  $providerCustomerId  An existing gateway customer id for this
     *                                           billable, if the host app already has one — null to create one.
     * @param  string  $customerEmail  Used only when a new gateway customer needs creating.
     * @param  string  $plan  A plan slug from config('laravel-payments.plans').
     * @param  array<string, mixed>  $options  Driver-specific extras (e.g. success_url/cancel_url).
     */
    public function checkout(
        ?string $providerCustomerId,
        string $customerEmail,
        string $plan,
        array $options = [],
    ): CheckoutResult;

    /**
     * Cancel a subscription by the gateway's own subscription identifier.
     */
    public function cancelSubscription(string $providerSubscriptionId): void;

    /**
     * The plan slug currently active for a gateway customer, or null if they
     * have no active subscription. Looked up live from the gateway — this
     * package keeps no local cache of subscription state.
     */
    public function currentPlan(string $providerCustomerId): ?string;
}
