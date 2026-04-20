<?php

use App\Jobs\ProcessPlexWebhookBatch;
use App\Support\PlexWebhookBatchStore;
use App\Support\PlexWebhookNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.plex.webhook_secret' => 'test-secret',
        'services.plex.webhook_debounce_seconds' => 30,
        'services.plex.webhook_max_batch_seconds' => 3600,
        'services.plex.webhook_added_at_max_age_minutes' => 15,
        'services.plex.webhook_queue' => 'plex-webhooks',
    ]);
});

function plexPayload(string $event = 'library.new', string $type = 'movie', array $metadata = [], array $server = []): string
{
    return json_encode([
        'event' => $event,
        'Metadata' => array_merge([
            'type' => $type,
            'title' => 'Test Movie',
            'year' => 2024,
            'ratingKey' => 'movie-100',
            'key' => '/library/metadata/movie-100',
            'guid' => 'plex://movie/movie-100',
            'addedAt' => now()->timestamp,
        ], $metadata),
        'Server' => array_merge([
            'uuid' => 'server-uuid-123',
            'title' => 'My Plex Server',
        ], $server),
    ]);
}

function decodePayload(string $payload): array
{
    return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
}

it('stores a movie in a versioned batch and dispatches a delayed job', function () {
    Queue::fake();

    $payload = plexPayload();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => $payload,
    ])->assertOk();

    $normalized = app(PlexWebhookNormalizer::class)->normalize(decodePayload($payload));
    $batch = app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']);

    expect($batch)->not->toBeNull()
        ->and($batch['server_name'])->toBe('My Plex Server')
        ->and($batch['group_type'])->toBe('movie')
        ->and($batch['version'])->toBe(1)
        ->and($batch['items'])->toHaveCount(1)
        ->and($batch['items'][$normalized['item_key']])->toMatchArray([
            'media_type' => 'movie',
            'title' => 'Test Movie',
            'year' => 2024,
            'rating_key' => 'movie-100',
            'guid' => 'plex://movie/movie-100',
        ]);

    Queue::assertPushed(ProcessPlexWebhookBatch::class, function (ProcessPlexWebhookBatch $job) use ($normalized) {
        return $job->serverUuid === 'server-uuid-123'
            && $job->groupKey === $normalized['group_key']
            && $job->version === 1
            && $job->queue === 'plex-webhooks';
    });
});

it('groups episode webhooks by grandparent rating key and stores ancestor identifiers', function () {
    Queue::fake();

    $payload = plexPayload('library.new', 'episode', [
        'title' => 'The One Where They All Find Out',
        'ratingKey' => 'episode-500',
        'parentRatingKey' => 'season-50',
        'grandparentRatingKey' => 'show-5',
        'key' => '/library/metadata/episode-500',
        'parentKey' => '/library/metadata/season-50',
        'grandparentKey' => '/library/metadata/show-5',
        'guid' => 'plex://episode/episode-500',
        'grandparentTitle' => 'Friends',
        'parentIndex' => 5,
        'index' => 14,
        'addedAt' => now()->timestamp,
    ]);

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => $payload,
    ])->assertOk();

    $normalized = app(PlexWebhookNormalizer::class)->normalize(decodePayload($payload));
    $batch = app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']);

    expect($normalized['group_key'])->toBe('show:grandparent-rating-key:show-5')
        ->and($batch['group_type'])->toBe('show')
        ->and($batch['items'][$normalized['item_key']])->toMatchArray([
            'media_type' => 'episode',
            'title' => 'The One Where They All Find Out',
            'show_title' => 'Friends',
            'season' => 5,
            'episode_number' => 14,
            'rating_key' => 'episode-500',
            'parent_rating_key' => 'season-50',
            'grandparent_rating_key' => 'show-5',
        ]);
});

it('increments the batch version when a new item extends the debounce window', function () {
    Queue::fake();

    $firstPayload = plexPayload('library.new', 'episode', [
        'title' => 'Episode One',
        'ratingKey' => 'episode-1',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 1,
    ]);

    $secondPayload = plexPayload('library.new', 'episode', [
        'title' => 'Episode Two',
        'ratingKey' => 'episode-2',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 2,
    ]);

    $this->post('/api/webhooks/plex/test-secret', ['payload' => $firstPayload])->assertOk();
    $this->post('/api/webhooks/plex/test-secret', ['payload' => $secondPayload])->assertOk();

    $normalized = app(PlexWebhookNormalizer::class)->normalize(decodePayload($firstPayload));
    $batch = app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']);

    expect($batch['version'])->toBe(2)
        ->and($batch['items'])->toHaveCount(2);

    Queue::assertPushed(ProcessPlexWebhookBatch::class, 2);
});

it('falls back to grandparent key when grandparent rating key is missing', function () {
    Queue::fake();

    $payload = plexPayload('library.new', 'episode', [
        'ratingKey' => 'episode-500',
        'grandparentRatingKey' => null,
        'grandparentKey' => '/library/metadata/show-5',
        'grandparentTitle' => 'Friends',
        'parentIndex' => 5,
        'index' => 14,
    ]);

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => $payload,
    ])->assertOk();

    $normalized = app(PlexWebhookNormalizer::class)->normalize(decodePayload($payload));

    expect($normalized['group_key'])->toBe('show:grandparent-key:/library/metadata/show-5');
});

it('rejects webhook when addedAt is older than max age', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'movie', [
            'addedAt' => now()->subHours(2)->timestamp,
        ]),
    ])->assertOk();

    Queue::assertNothingPushed();
});

it('logs a warning when rating key is missing but still batches the payload', function () {
    Queue::fake();
    Log::spy();

    $payload = plexPayload('library.new', 'movie', [
        'ratingKey' => null,
        'key' => null,
        'guid' => null,
    ]);

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => $payload,
    ])->assertOk();

    $normalized = app(PlexWebhookNormalizer::class)->normalize(decodePayload($payload));
    $batch = app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']);

    expect($batch)->not->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        return $message === 'Plex webhook identifiers degraded'
            && in_array('missing_rating_key', $context['warnings'], true);
    });
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
