<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('retries on connection exception', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            return Http::failedConnection();
        }

        return Http::response(['ok' => true]);
    });

    $response = Http::resilient()->get('https://example.com/test');

    expect($response->json('ok'))->toBeTrue();
    expect($attempts)->toBe(2);
});

it('retries on 429 rate limit', function () {
    Http::fake([
        'example.com/*' => Http::sequence()
            ->push('Rate limited', 429)
            ->push(['ok' => true]),
    ]);

    $response = Http::resilient()->get('https://example.com/test');

    expect($response->json('ok'))->toBeTrue();
    Http::assertSentCount(2);
});

it('retries on transient server errors', function (int $status) {
    Http::fake([
        'example.com/*' => Http::sequence()
            ->push('Server Error', $status)
            ->push(['ok' => true]),
    ]);

    $response = Http::resilient()->get('https://example.com/test');

    expect($response->json('ok'))->toBeTrue();
    Http::assertSentCount(2);
})->with([408, 502, 503, 504]);

it('does not retry on 500 internal server error', function () {
    Http::fake([
        'example.com/*' => Http::response('Internal Server Error', 500),
    ]);

    Http::resilient()->get('https://example.com/test');
})->throws(RequestException::class);

it('does not retry on 404 not found', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    Http::resilient()->get('https://example.com/test');
})->throws(RequestException::class);
