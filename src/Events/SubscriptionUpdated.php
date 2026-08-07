<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a subscription's status or plan changes at the gateway (plan
 * upgrades/downgrades, trial ending, moving to past_due, etc).
 */
final class SubscriptionUpdated
{
    use Dispatchable;

    /** @param  array<string, mixed>  $raw  The full webhook payload, for anything not modeled above. */
    public function __construct(
        public readonly string $providerCustomerId,
        public readonly string $providerSubscriptionId,
        public readonly string $status,
        public readonly ?string $providerPriceId,
        public readonly array $raw,
    ) {}
}
