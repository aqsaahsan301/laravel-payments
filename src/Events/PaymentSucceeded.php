<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a charge/invoice succeeds. Host apps listen for this rather than
 * anything gateway-specific — the same listener keeps working if the active
 * gateway changes from Stripe to something else.
 */
final class PaymentSucceeded
{
    use Dispatchable;

    /** @param  array<string, mixed>  $raw  The full webhook payload, for anything not modeled above. */
    public function __construct(
        public readonly string $providerCustomerId,
        public readonly ?string $providerSubscriptionId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly array $raw,
    ) {}
}
