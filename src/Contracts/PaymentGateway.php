<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Contracts;

use Aqsaahsan301\LaravelPayments\DataTransferObjects\ChargeResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The base seam every gateway driver implements — deliberately shaped around
 * what *every* payment gateway can do, not just subscription/card-based ones.
 * FPX, Billplz, ToyyibPay, DuitNow QR and similar are often one-time/invoice
 * gateways with no subscription concept at all; a contract that assumed
 * subscriptions would make them impossible to implement honestly.
 *
 * Drivers that also support recurring billing (Stripe, and presumably
 * Curlec/Razorpay) additionally implement SupportsSubscriptions. Host app
 * code that wants subscription features checks
 * `$gateway instanceof SupportsSubscriptions` rather than assuming every
 * driver has it.
 *
 * Deliberately stateless and host-app-model-agnostic: no method accepts or
 * returns an Eloquent model, because the package has no idea what a host
 * app's "billable" looks like. Identifiers are passed in as plain strings the
 * host app is responsible for persisting (see ChargeResult), and inbound
 * webhook data comes back out as Laravel events (PaymentSucceeded,
 * SubscriptionCancelled, PaymentFailed, SubscriptionUpdated) rather than
 * database writes this package would have to own.
 */
interface PaymentGateway
{
    /**
     * Start a one-time payment — the one operation every gateway can do,
     * subscription-capable or not.
     *
     * @param  string  $customerEmail  Used only when a new gateway customer needs creating.
     * @param  int  $amount  In the smallest currency unit (e.g. cents).
     * @param  string  $currency  ISO 4217 currency code (e.g. "usd", "myr").
     * @param  array<string, mixed>  $options  Driver-specific extras (e.g. success_url/cancel_url).
     */
    public function charge(
        string $customerEmail,
        int $amount,
        string $currency,
        array $options = [],
    ): ChargeResult;

    /**
     * Handle an inbound webhook request from the gateway. Verifies the
     * request is genuinely from the gateway, then fires this package's own
     * events so host apps can react without knowing which gateway is active.
     */
    public function handleWebhook(Request $request): Response;
}
