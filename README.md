<div align="center">
    <h1>Laravel Payments</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/aqsaahsan301/laravel-payments"><img src="https://img.shields.io/packagist/v/aqsaahsan301/laravel-payments.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/aqsaahsan301/laravel-payments"><img src="https://img.shields.io/packagist/php-v/aqsaahsan301/laravel-payments.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/aqsaahsan301/laravel-payments"><img src="https://badge.laravel.cloud/badge/aqsaahsan301/laravel-payments?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/aqsaahsan301/laravel-payments/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/aqsaahsan301/laravel-payments/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/aqsaahsan301/laravel-payments"><img src="https://img.shields.io/packagist/dt/aqsaahsan301/laravel-payments.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Gateway-agnostic payment and subscription billing for Laravel.

## Installation

You can install the package via Composer:

```bash
composer require aqsaahsan301/laravel-payments
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="laravel-payments"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="laravel-payments-config"
```

## Why gateway-agnostic

[Laravel Cashier](https://laravel.com/docs/billing) is excellent, but it's Stripe-only by design — it owns a `subscriptions` table, a `Billable` Eloquent trait, and a whole persistence layer built around Stripe's model. For clients who need a local gateway instead (Billplz, ToyyibPay, Curlec/Razorpay — common where FPX, GrabPay, or TnG matter), that coupling means starting over.

This package takes a different shape:

- **A base contract shaped around what *every* gateway can do.** `PaymentGateway` has just `charge()` (a one-time payment) and `handleWebhook()`. It's deliberately *not* shaped around Stripe's subscription model — plenty of real gateways (FPX, Billplz, ToyyibPay, DuitNow QR) are one-time/invoice-only with no subscription concept at all, and a contract that assumed subscriptions would make them impossible to implement honestly.
- **Subscriptions are a separate, optional contract.** Drivers whose gateway actually supports recurring billing (Stripe, presumably Curlec/Razorpay) additionally implement `SupportsSubscriptions` — `checkout()`, `cancelSubscription()`, `currentPlan()`. Host app code checks `$gateway instanceof SupportsSubscriptions` (or resolves `SupportsSubscriptions` from the container directly) before offering recurring-billing UI, rather than assuming every driver has it.
- **`PaymentManager` resolves the configured driver** the same way Laravel's own `MailManager`/`QueueManager` do. Set `PAYMENT_GATEWAY=stripe` in `.env`; swapping to a different driver later is a config change plus a new driver class, not a rewrite.
- **No database, no Eloquent models, no opinion on your schema.** The package is completely stateless. `charge()`/`checkout()` take and return plain identifiers (strings) that *you* persist however you like. Inbound webhooks come back out as this package's own Laravel events — `PaymentSucceeded`, `PaymentFailed`, `SubscriptionCancelled`, `SubscriptionUpdated` — carrying plain data, not gateway SDK objects. You listen for those and update your own tables. This is what makes the package reusable across projects with completely different schemas (a `User` is billable in one app, an `Organization` in another — the package doesn't care).

### Design principle: Interface Segregation (SOLID)

The `PaymentGateway` / `SupportsSubscriptions` split (shipped in `v1.1.0`) is a direct application of the **Interface Segregation Principle** — "no client should be forced to depend on methods it does not use." Before the split, `PaymentGateway` carried `checkout()`, `cancelSubscription()`, and `currentPlan()` as required methods, which meant a one-time/invoice-only gateway (FPX, Billplz, ToyyibPay) had no honest way to implement it — it would have to either throw `NotSupportedException` from three methods or fake a subscription concept it doesn't have. Both are ISP violations: the *interface* was shaped around one client's needs (Stripe) and imposed on every implementer.

Splitting the fat interface into a lean base contract (`charge()`, `handleWebhook()` — what every gateway can do) plus a segregated, opt-in contract (`SupportsSubscriptions` — what only some gateways can do) means:

- A driver only implements what its gateway actually supports. No dead methods, no `throw` stubs.
- Consumers depend on the narrowest interface that satisfies them — code that only ever charges type-hints `PaymentGateway`; code that needs recurring billing type-hints `SupportsSubscriptions` and gets a clear container-resolution error if the active driver doesn't implement it, instead of a runtime "method does not exist" surprise.
- Adding a gateway with a different capability shape (say, one that supports refunds but not subscriptions) is a new optional interface, not a widening of the base contract that breaks every other driver.

## Configuration

```bash
php artisan vendor:publish --tag="laravel-payments-config"
```

```env
PAYMENT_GATEWAY=stripe

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

PAYMENT_GATEWAY_WEBHOOK_PATH=laravel-payments/webhook   # optional, this is the default
```

Map your app's plan slugs to each gateway's own price/plan ids in `config/laravel-payments.php`:

```php
'plans' => [
    'starter' => [
        'stripe' => 'price_123...',
    ],
    'pro' => [
        'stripe' => 'price_456...',
    ],
],
```

The package registers its webhook route automatically at `laravel-payments/webhook` (or wherever `webhook_path` points). **Exclude it from CSRF verification** in your host app's `bootstrap/app.php` — the gateway calls it directly and can't supply a token:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'laravel-payments/webhook',
    ]);
})
```

Then point your gateway's dashboard (Stripe's webhook settings, for example) at `https://your-app.test/laravel-payments/webhook`, and copy the signing secret it gives you into `STRIPE_WEBHOOK_SECRET`.

## Usage

### A one-time payment (every gateway supports this)

```php
use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;

class CheckoutController
{
    public function pay(Request $request, PaymentGateway $gateway)
    {
        $result = $gateway->charge(
            customerEmail: $request->user()->email,
            amount: 4900, // smallest currency unit — $49.00
            currency: 'usd',
            options: [
                'success_url' => route('orders.show', $order).'?paid=1',
                'cancel_url' => route('orders.show', $order).'?paid=0',
            ],
        );

        return redirect()->away($result->url);
    }
}
```

### Starting a subscription checkout (only for drivers that support it)

```php
use Aqsaahsan301\LaravelPayments\Contracts\SupportsSubscriptions;

class BillingController
{
    public function checkout(Request $request, SupportsSubscriptions $gateway)
    {
        $organization = $request->user()->organization;

        $result = $gateway->checkout(
            providerCustomerId: $organization->billing_customer_id, // null if they don't have one yet
            customerEmail: $request->user()->email,
            plan: 'starter',
            options: [
                'success_url' => route('billing.show').'?checkout=success',
                'cancel_url' => route('billing.show').'?checkout=cancelled',
            ],
        );

        // Persist the customer id yourself — the package never does this for you.
        $organization->update(['billing_customer_id' => $result->providerCustomerId]);

        return redirect()->away($result->url);
    }
}
```

Type-hinting `SupportsSubscriptions` (rather than `PaymentGateway`) means the container throws immediately with a clear message if `PAYMENT_GATEWAY` is ever pointed at a driver that doesn't support subscriptions — instead of failing later with a "method does not exist" error. If your app needs to conditionally show recurring-billing UI only when it's available, resolve `PaymentGateway` and check `instanceof SupportsSubscriptions` instead of hard-depending on it.

Both `charge()` and `checkout()` return a URL to redirect the browser to — for hosted-checkout gateways like Stripe, that has to be a real top-level navigation (`redirect()->away(...)` in a controller, or `window.location.href = ...` from an SPA/Inertia frontend), not an XHR-driven route visit, since the response goes off-site.

### Reacting to webhook events

```php
// In a listener, registered however you normally register listeners:

use Aqsaahsan301\LaravelPayments\Events\SubscriptionCancelled;

class RevokeAccessOnCancellation
{
    public function handle(SubscriptionCancelled $event): void
    {
        Organization::where('billing_customer_id', $event->providerCustomerId)
            ->update(['plan' => null]);
    }
}
```

Available events: `PaymentSucceeded`, `PaymentFailed`, `SubscriptionCancelled`, `SubscriptionUpdated`. Each carries plain scalars (`providerCustomerId`, `providerSubscriptionId`, amounts, currency, status) plus a `raw` array with the full webhook payload for anything not modeled explicitly.

### Checking the current plan / cancelling

```php
$gateway->currentPlan($organization->billing_customer_id);      // 'starter' | 'pro' | null
$gateway->cancelSubscription($organization->billing_subscription_id);
```

## Adding a new driver

Every driver is a class implementing `Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway` (and `SupportsSubscriptions` too, only if the gateway genuinely has recurring billing) — `StripeDriver` (`src/Drivers/StripeDriver.php`) is the reference implementation to copy the shape of. To add, say, a Billplz driver (one-time/invoice-only, no subscriptions):

1. **Write the driver.** `src/Drivers/BillplzDriver.php implements PaymentGateway`. It's the *only* class allowed to import anything from Billplz's SDK/API client — everything else in the package (and in host apps) depends on the contract, never on your driver directly. Implement `charge()` and `handleWebhook()`; skip `SupportsSubscriptions` entirely if the gateway has no subscription concept — that's the whole point of the split.
2. **Translate provider events into this package's own events.** In your `handleWebhook()`, map Billplz's webhook payload fields onto `PaymentSucceeded`/`PaymentFailed` (and `SubscriptionCancelled`/`SubscriptionUpdated` too, if you implemented `SupportsSubscriptions`) and dispatch those — don't invent new event classes per driver, or host app listeners have to know which gateway is active, defeating the point.
3. **Register it.** Either add a `createBillplzDriver()` method to `PaymentManager` (the built-in, Laravel `Manager`-style way — see `createStripeDriver()`), or call `$manager->extendDriver('billplz', BillplzDriver::class)` from your own service provider if you're shipping the driver as a separate package.
4. **Test it the same way `StripeDriverTest`/`StripeWebhookEventsTest` do**: install a fake HTTP client for your provider's SDK (or bind a mock client in the container) so tests never hit the network, then assert on `charge()`'s returned `ChargeResult`, on which events `handleWebhook()` dispatches for which payloads, and that an invalid signature is rejected.
5. Nothing in `PaymentGateway`, `SupportsSubscriptions`, `PaymentManager`, the routes file, the events, or any host app code should need to change. If it does, that's a sign the new driver needs something the contract doesn't offer yet — widen the contract, not a driver-specific escape hatch.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Payments! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Aqsa Ahsan](https://github.com/aqsaahsan301)
- [All Contributors](../../contributors)

## License

Laravel Payments is open-sourced software licensed under the [MIT license](LICENSE.md).
