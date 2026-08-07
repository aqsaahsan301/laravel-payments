<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Drivers;

use Aqsaahsan301\LaravelPayments\Contracts\PaymentGateway;
use Aqsaahsan301\LaravelPayments\DataTransferObjects\CheckoutResult;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The only class in this package that talks to the Stripe API. Uses the raw
 * stripe/stripe-php SDK directly rather than Laravel Cashier — Cashier is
 * Stripe-only by design (it owns a `subscriptions` table, a Billable trait
 * tied to Eloquent, etc.), which would defeat the point of a gateway-agnostic
 * package. Every future driver (Billplz, ToyyibPay, Curlec) follows this same
 * shape: implement PaymentGateway, talk to exactly one provider's API,
 * translate its responses into this package's own plain types and events.
 */
class StripeDriver implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly ConfigRepository $config,
    ) {}

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

        // Day 4 dispatches this package's own Laravel events (PaymentSucceeded,
        // SubscriptionCancelled, PaymentFailed, SubscriptionUpdated) based on
        // $event->type here, so host apps never need to know this came from Stripe.

        return new Response('Webhook handled', 200);
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
