# Release Notes

## [Unreleased](https://github.com/aqsaahsan301/laravel-payments/compare/v1.1.0...1.x)

## [v1.1.0](https://github.com/aqsaahsan301/laravel-payments/compare/v1.0.0...v1.1.0) - 2026-08-07

**Breaking change**: `PaymentGateway` split in two, so gateways with no subscription concept (FPX, Billplz, ToyyibPay, DuitNow QR — common local Malaysian gateways, often one-time/invoice-only) can implement the base contract honestly instead of being forced into a subscription shape they don't have.

- `PaymentGateway` is now just `charge()` (one-time payment, every gateway can do this) + `handleWebhook()`.
- `checkout()`, `cancelSubscription()`, and `currentPlan()` moved to a new, optional `SupportsSubscriptions` interface. `StripeDriver` implements both. Host app code checks `$gateway instanceof SupportsSubscriptions` (or type-hints/resolves `SupportsSubscriptions` directly, which throws a clear error at resolution time if the configured driver doesn't support it) before offering recurring-billing UI.
- New `ChargeResult` DTO (mirrors `CheckoutResult`'s shape: a redirect URL + an optional provider customer id) returned by `charge()`.
- `StripeDriver::charge()` uses a Stripe Checkout Session in `payment` mode (as opposed to `checkout()`'s `subscription` mode).
- `handleWebhook()` now also handles `checkout.session.completed` for one-time payments, dispatching `PaymentSucceeded` when the session's `mode` is `payment` (subscription-mode sessions still get their `PaymentSucceeded` from `invoice.payment_succeeded`, unchanged).
- **Upgrading**: if you were calling `checkout()`/`cancelSubscription()`/`currentPlan()` via the `PaymentGateway` contract, switch to type-hinting/resolving `SupportsSubscriptions` instead.

## [v1.0.0](https://github.com/aqsaahsan301/laravel-payments/compare/v0.1.0...v1.0.0) - 2026-08-07

Initial stable release.

- `PaymentGateway` contract: `checkout()`, `handleWebhook()`, `cancelSubscription()`, `currentPlan()` — gateway-agnostic, host-app-model-agnostic (plain string identifiers in/out, no Eloquent models, no database owned by the package)
- `PaymentManager` resolves the configured driver, the same pattern as Laravel's `MailManager`/`QueueManager`; `PAYMENT_GATEWAY` env var picks the driver
- `StripeDriver`: the built-in driver, using `stripe/stripe-php` directly (not Cashier)
- `PaymentSucceeded`, `PaymentFailed`, `SubscriptionCancelled`, `SubscriptionUpdated` events — dispatched from webhook handling instead of the package touching a host app's schema
- A registered webhook route (path configurable via `laravel-payments.webhook_path`)
- Full Pest test suite via Testbench with a fake Stripe HTTP client — no live keys or network calls needed to run it

## [v0.1.0](https://github.com/aqsaahsan301/laravel-payments/compare/...v0.1.0) - 2026-08-06

Initial pre-release (package skeleton only).
