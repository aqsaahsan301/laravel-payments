<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a charge/invoice fails. Host apps typically use this to notify
 * the customer or flag the account, without needing to know which gateway
 * produced the failure.
 */
final class PaymentFailed
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
