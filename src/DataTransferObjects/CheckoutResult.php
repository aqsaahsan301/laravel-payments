<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\DataTransferObjects;

/**
 * Result of starting a checkout. The host app redirects the browser to `url`,
 * and — if it didn't already have one — persists `providerCustomerId` against
 * whatever "billable" it has on its own side. The package never stores this
 * itself; it has no database and no opinion on the host app's schema.
 */
final class CheckoutResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $providerCustomerId,
    ) {}
}
