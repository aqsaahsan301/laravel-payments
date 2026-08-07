<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\Tests\Support\FakeStripeHttpClient;
use Aqsaahsan301\LaravelPayments\Tests\TestCase;
use Illuminate\Http\Request;
use Stripe\ApiRequestor;

uses(TestCase::class)->in(__DIR__);

function fakeStripeHttp(): FakeStripeHttpClient
{
    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    return $fake;
}

function signedStripeWebhookRequest(array $payload, string $secret): Request
{
    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return Request::create(
        '/webhooks/stripe',
        'POST',
        content: $body,
        server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"],
    );
}
