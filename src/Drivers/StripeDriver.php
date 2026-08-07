<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Drivers;

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Aqsaahsan301\LaravelPayments\Contracts\SupportsSubscriptions;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\ChargeResult;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;
use Aqsaahsan301\LaravelPayments\Events\PaymentFailed;
use Aqsaahsan301\LaravelPayments\Events\PaymentSucceeded;
use Aqsaahsan301\LaravelPayments\Events\SubscriptionCancelled;
use Aqsaahsan301\LaravelPayments\Events\SubscriptionUpdated;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The only class in this package that talks to the Stripe API. Uses the raw
 * stripe/stripe-php SDK directly rather than Laravel Cashier — Cashier is
 * Stripe-only by design (it owns a `subscriptions` table, a Billable trait
 * tied to Eloquent, etc.), which would defeat the point of a gateway-agnostic
 * package. Every future driver (Billplz, ToyyibPay, Curlec) follows this same
 * shape: implement PaymentGateway (and SupportsSubscriptions if the gateway
 * actually has subscriptions — Billplz/ToyyibPay/DuitNow QR generally don't),
 * talk to exactly one provider's API, translate its responses into this
 * package's own plain types and events.
 */
class StripeDriver implements PaymentGateway, SupportsSubscriptions
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly ConfigRepository $config,
        private readonly Dispatcher $events,
    ) {}

    public function charge(
        string $customerEmail,
        int $amount,
        string $currency,
        array $options = [],
    ): ChargeResult {
        foreach (['success_url', 'cancel_url'] as $required) {
            if (empty($options[$required])) {
                throw new InvalidArgumentException("The '{$required}' option is required to start a Stripe charge.");
            }
        }

        $customerId = $options['provider_customer_id'] ?? $this->client->customers->create([
            'email' => $customerEmail,
        ])->id;

        $session = $this->client->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => $options['description'] ?? 'Payment',
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $options['success_url'],
            'cancel_url' => $options['cancel_url'],
        ]);

        return new ChargeResult(
            url: (string) $session->url,
            providerCustomerId: $customerId,
        );
    }

    public function checkout(
        ?string $providerCustomerId,
        string $customerEmail,
        string $plan,
        array $options = [],
    ): CheckoutResult {
        $price = $this->config->get("laravel-payments.plans.{$plan}.stripe");

        if (! is_string($price) || $price === '') {
            throw new InvalidArgumentException(
                "Unknown plan [{$plan}]: set laravel-payments.plans.{$plan}.stripe to a Stripe price id.",
            );
        }

        foreach (['success_url', 'cancel_url'] as $required) {
            if (empty($options[$required])) {
                throw new InvalidArgumentException("The '{$required}' option is required to start a Stripe checkout.");
            }
        }

        $customerId = $providerCustomerId ?? $this->client->customers->create([
            'email' => $customerEmail,
        ])->id;

        $session = $this->client->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $price,
                'quantity' => $options['quantity'] ?? 1,
            ]],
            'success_url' => $options['success_url'],
            'cancel_url' => $options['cancel_url'],
        ]);

        return new CheckoutResult(
            url: (string) $session->url,
            providerCustomerId: $customerId,
        );
    }

    public function handleWebhook(Request $request): Response
    {
        $event = $this->verifyAndParseWebhook($request);

        $this->dispatchPackageEvent($event);

        return new Response('Webhook handled', 200);
    }

    /**
     * Translates a Stripe event into this package's own gateway-agnostic
     * event, and dispatches that instead. Host app listeners depend on
     * PaymentSucceeded/PaymentFailed/SubscriptionCancelled/SubscriptionUpdated
     * — never on anything Stripe-specific — so they keep working unchanged
     * if the active gateway ever changes.
     */
    private function dispatchPackageEvent(Event $event): void
    {
        /** @var StripeObject $object */
        $object = $event->data->object;

        match ($event->type) {
            // Fires for both checkout modes; only relevant here for one-time
            // charges (mode=payment) — subscription checkouts get their
            // PaymentSucceeded from invoice.payment_succeeded instead, once
            // Stripe generates the first invoice for the new subscription.
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object),
            'invoice.payment_succeeded' => $this->events->dispatch(new PaymentSucceeded(
                providerCustomerId: (string) $object['customer'],
                providerSubscriptionId: $this->nullableString($object['subscription'] ?? null),
                amount: (int) $object['amount_paid'],
                currency: (string) $object['currency'],
                raw: $object->toArray(),
            )),
            'invoice.payment_failed' => $this->events->dispatch(new PaymentFailed(
                providerCustomerId: (string) $object['customer'],
                providerSubscriptionId: $this->nullableString($object['subscription'] ?? null),
                amount: (int) $object['amount_due'],
                currency: (string) $object['currency'],
                raw: $object->toArray(),
            )),
            'customer.subscription.deleted' => $this->events->dispatch(new SubscriptionCancelled(
                providerCustomerId: (string) $object['customer'],
                providerSubscriptionId: (string) $object['id'],
                raw: $object->toArray(),
            )),
            'customer.subscription.updated' => $this->events->dispatch(new SubscriptionUpdated(
                providerCustomerId: (string) $object['customer'],
                providerSubscriptionId: (string) $object['id'],
                status: (string) $object['status'],
                providerPriceId: $this->firstItemPriceId($object),
                raw: $object->toArray(),
            )),
            default => null,
        };
    }

    private function handleCheckoutSessionCompleted(StripeObject $session): void
    {
        if ($session['mode'] !== 'payment') {
            return;
        }

        $this->events->dispatch(new PaymentSucceeded(
            providerCustomerId: (string) $session['customer'],
            providerSubscriptionId: null,
            amount: (int) $session['amount_total'],
            currency: (string) $session['currency'],
            raw: $session->toArray(),
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function firstItemPriceId(StripeObject $subscription): ?string
    {
        $items = $subscription['items']['data'] ?? [];

        return isset($items[0]['price']['id']) ? (string) $items[0]['price']['id'] : null;
    }

    private function verifyAndParseWebhook(Request $request): Event
    {
        $secret = $this->config->get('laravel-payments.gateways.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new InvalidArgumentException(
                'STRIPE_WEBHOOK_SECRET is not configured — refusing to process an unverifiable webhook.',
            );
        }

        try {
            return Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature') ?? '',
                $secret,
            );
        } catch (SignatureVerificationException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }
    }

    public function cancelSubscription(string $providerSubscriptionId): void
    {
        $this->client->subscriptions->cancel($providerSubscriptionId);
    }

    public function currentPlan(string $providerCustomerId): ?string
    {
        $subscriptions = $this->client->subscriptions->all([
            'customer' => $providerCustomerId,
            'status' => 'active',
            'limit' => 1,
        ]);

        if ($subscriptions->count() === 0) {
            return null;
        }

        $priceId = $subscriptions->data[0]->items->data[0]->price->id;

        foreach ((array) $this->config->get('laravel-payments.plans', []) as $slug => $plan) {
            if (($plan['stripe'] ?? null) === $priceId) {
                return (string) $slug;
            }
        }

        return null;
    }
}
