<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a subscription is cancelled at the gateway. Host apps typically
 * revoke access or downgrade the account in response to this.
 */
final class SubscriptionCancelled
{
    use Dispatchable;

    /** @param  array<string, mixed>  $raw  The full webhook payload, for anything not modeled above. */
    public function __construct(
        public readonly string $providerCustomerId,
        public readonly string $providerSubscriptionId,
        public readonly array $raw,
    ) {}
}
