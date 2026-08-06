<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Contracts;

use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The seam every gateway driver implements, and the only thing host apps
 * (and this package's own PaymentManager consumers) depend on.
 *
 * Deliberately stateless and host-app-model-agnostic: no method accepts or
 * returns an Eloquent model, because the package has no idea what a host
 * app's "billable" looks like. Identifiers are passed in as plain strings the
 * host app is responsible for persisting (see CheckoutResult), and inbound
 * webhook data comes back out as Laravel events (PaymentSucceeded,
 * SubscriptionCancelled, PaymentFailed, SubscriptionUpdated) rather than
 * database writes this package would have to own.
 */
interface PaymentGateway
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
     * Handle an inbound webhook request from the gateway. Verifies the
     * request is genuinely from the gateway, then fires this package's own
     * events so host apps can react without knowing which gateway is active.
     */
    public function handleWebhook(Request $request): Response;

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
