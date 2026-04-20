<?php

use App\Support\PlexWebhookBatchStore;
use App\Support\PlexWebhookNormalizer;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    config([
        'services.plex.webhook_debounce_seconds' => 30,
        'services.plex.webhook_max_batch_seconds' => 3600,
    ]);
});

function batchPayload(string $type = 'movie', array $metadata = [], array $server = []): array
{
    return [
        'event' => 'library.new',
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
            'uuid' => 'server-123',
            'title' => 'My Server',
        ], $server),
    ];
}

it('caps the flush time at the hard deadline', function () {
    $store = app(PlexWebhookBatchStore::class);
    $normalizer = app(PlexWebhookNormalizer::class);

    $firstNormalized = $normalizer->normalize(batchPayload('episode', [
        'title' => 'Episode One',
        'ratingKey' => 'episode-1',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 1,
    ]));

    $firstBatch = $store->upsert($firstNormalized);

    $this->travel(3590)->seconds();

    $secondNormalized = $normalizer->normalize(batchPayload('episode', [
        'title' => 'Episode Two',
        'ratingKey' => 'episode-2',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 2,
    ]));

    $secondBatch = $store->upsert($secondNormalized);

    expect($secondBatch['flush_at'])->toBe($firstBatch['hard_deadline_at']);
});

it('keeps newer items in the batch after older items are finalized', function () {
    $store = app(PlexWebhookBatchStore::class);
    $normalizer = app(PlexWebhookNormalizer::class);

    $firstNormalized = $normalizer->normalize(batchPayload('episode', [
        'title' => 'Episode One',
        'ratingKey' => 'episode-1',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 1,
    ]));

    $store->upsert($firstNormalized);

    $secondNormalized = $normalizer->normalize(batchPayload('episode', [
        'title' => 'Episode Two',
        'ratingKey' => 'episode-2',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 2,
    ]));

    $secondBatch = $store->upsert($secondNormalized);

    $remainingBatch = $store->finalizeProcessedItems(
        $firstNormalized['server_uuid'],
        $firstNormalized['group_key'],
        1,
        [$firstNormalized['item_key']],
    );

    expect($remainingBatch)->not->toBeNull()
        ->and($remainingBatch['version'])->toBe($secondBatch['version'])
        ->and($remainingBatch['items'])->toHaveCount(1)
        ->and($remainingBatch['items'])->toHaveKey($secondNormalized['item_key']);
});
