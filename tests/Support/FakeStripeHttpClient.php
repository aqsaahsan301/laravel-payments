<?php

declare(strict_types=1);

namespace Aqsaahsan301\LaravelPayments\Tests\Support;

use RuntimeException;
use Stripe\HttpClient\ClientInterface;

/**
 * A queue-based fake for Stripe's HTTP client, installed via
 * \Stripe\ApiRequestor::setHttpClient() so StripeDriver's tests never make a
 * real network call. Responses are consumed in the order they're queued;
 * every request made is recorded for assertions.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{status: int, body: array<string, mixed>}> */
    private array $responses = [];

    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @param array<string, mixed> $body */
    public function queue(int $status, array $body): void
    {
        $this->responses[] = ['status' => $status, 'body' => $body];
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<string, mixed>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        if ($this->responses === []) {
            throw new RuntimeException("No fake Stripe response queued for {$method} {$absUrl}");
        }

        $response = array_shift($this->responses);

        return [json_encode($response['body']), $response['status'], []];
    }
}
