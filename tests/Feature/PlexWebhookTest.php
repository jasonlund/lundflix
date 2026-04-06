<?php

use App\Jobs\ProcessPlexWebhookBatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.plex.webhook_secret' => 'test-secret']);
    config(['services.plex.webhook_debounce_seconds' => 30]);
    config(['services.plex.webhook_added_at_max_age_minutes' => 15]);
});

function plexPayload(string $event = 'library.new', string $type = 'movie', array $metadata = [], array $server = []): string
{
    return json_encode([
        'event' => $event,
        'Metadata' => array_merge([
            'type' => $type,
            'title' => 'Test Movie',
            'year' => 2024,
            'addedAt' => now()->timestamp,
        ], $metadata),
        'Server' => array_merge([
            'uuid' => 'server-uuid-123',
            'title' => 'My Plex Server',
        ], $server),
    ]);
}

it('stores a movie in the cache batch and dispatches debounce job', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload(),
    ])->assertOk();

    $batch = Cache::get('plex-webhook:server-uuid-123');

    expect($batch)->not->toBeNull()
        ->and($batch['server_name'])->toBe('My Plex Server')
        ->and($batch['items'])->toHaveCount(1)
        ->and($batch['items'][0])->toMatchArray([
            'media_type' => 'movie',
            'title' => 'Test Movie',
            'year' => 2024,
        ]);

    Queue::assertPushed(ProcessPlexWebhookBatch::class, function ($job) {
        return $job->serverUuid === 'server-uuid-123';
    });
});

it('stores an episode in the cache batch with correct fields', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'episode', [
            'title' => 'The One Where They All Find Out',
            'grandparentTitle' => 'Friends',
            'parentIndex' => 5,
            'index' => 14,
            'addedAt' => now()->timestamp,
        ]),
    ])->assertOk();

    $batch = Cache::get('plex-webhook:server-uuid-123');

    expect($batch['items'][0])->toMatchArray([
        'media_type' => 'episode',
        'title' => 'The One Where They All Find Out',
        'show_title' => 'Friends',
        'season' => 5,
        'episode_number' => 14,
    ]);
});

it('rejects invalid tokens', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/wrong-token', [
        'payload' => plexPayload(),
    ])->assertForbidden();

    expect(Cache::get('plex-webhook:server-uuid-123'))->toBeNull();
});

it('ignores non-library.new events', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('media.play'),
    ])->assertOk();

    expect(Cache::get('plex-webhook:server-uuid-123'))->toBeNull();
    Queue::assertNothingPushed();
});

it('ignores unsupported media types', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'track'),
    ])->assertOk();

    expect(Cache::get('plex-webhook:server-uuid-123'))->toBeNull();
    Queue::assertNothingPushed();
});

it('rejects webhook when addedAt is older than max age', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'movie', [
            'addedAt' => now()->subHours(2)->timestamp,
        ]),
    ])->assertOk();

    expect(Cache::get('plex-webhook:server-uuid-123'))->toBeNull();
    Queue::assertNothingPushed();
});

it('rejects webhook when addedAt is missing', function () {
    Queue::fake();

    $payload = json_encode([
        'event' => 'library.new',
        'Metadata' => ['type' => 'movie', 'title' => 'No AddedAt Movie', 'year' => 2024],
        'Server' => ['uuid' => 'server-uuid-123', 'title' => 'My Plex Server'],
    ]);

    $this->post('/api/webhooks/plex/test-secret', ['payload' => $payload])->assertOk();

    expect(Cache::get('plex-webhook:server-uuid-123'))->toBeNull();
    Queue::assertNothingPushed();
});

it('accepts webhook when addedAt is within max age', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'movie', [
            'addedAt' => now()->subMinutes(5)->timestamp,
        ]),
    ])->assertOk();

    $batch = Cache::get('plex-webhook:server-uuid-123');

    expect($batch['items'])->toHaveCount(1);
});

it('accumulates multiple items in the same cache batch', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'movie', ['title' => 'Movie One', 'year' => 2020]),
    ])->assertOk();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'movie', ['title' => 'Movie Two', 'year' => 2021]),
    ])->assertOk();

    $batch = Cache::get('plex-webhook:server-uuid-123');

    expect($batch['items'])->toHaveCount(2);
});

it('handles malformed payload gracefully', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => 'not-json',
    ])->assertOk();

    Queue::assertNothingPushed();
});

it('handles missing payload gracefully', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret')->assertOk();

    Queue::assertNothingPushed();
});
