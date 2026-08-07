# Release Notes

## [Unreleased](https://github.com/aqsaahsan301/laravel-payments/compare/v1.0.0...1.x)

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
