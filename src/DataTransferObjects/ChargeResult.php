<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\DataTransferObjects;

/**
 * Result of starting a one-time charge. Shape mirrors CheckoutResult
 * deliberately — both are "redirect the browser, then persist what you get
 * back" — but kept as a distinct type since a one-time charge and a
 * subscription checkout are different operations with different callers
 * (PaymentGateway::charge() vs SupportsSubscriptions::checkout()).
 */
final class ChargeResult
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $providerCustomerId,
    ) {}
}
