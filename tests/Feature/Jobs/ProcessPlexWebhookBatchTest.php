<?php

use App\Enums\RequestItemStatus;
use App\Jobs\ProcessPlexWebhookBatch;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlexMediaServer;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\PlexLibraryNotification;
use App\Support\PlexWebhookBatchStore;
use App\Support\PlexWebhookNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.slack.enabled' => true,
        'services.slack.notifications.channel' => 'C12345',
        'services.plex.webhook_debounce_seconds' => 30,
        'services.plex.webhook_max_batch_seconds' => 3600,
        'services.plex.webhook_queue' => 'plex-webhooks',
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
    ]);
});

function webhookPayload(string $type = 'movie', array $metadata = [], array $server = []): array
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

function storeWebhookBatch(array $payload): array
{
    $normalized = app(PlexWebhookNormalizer::class)->normalize($payload);
    $batch = app(PlexWebhookBatchStore::class)->upsert($normalized);

    return [$normalized, $batch];
}

it('processes a due movie batch and sends a notification', function () {
    Notification::fake();

    [$normalized, $batch] = storeWebhookBatch(webhookPayload());

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect(app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']))->toBeNull();
    Notification::assertSentOnDemand(PlexLibraryNotification::class);
});

it('skips an early job before the flush window elapses', function () {
    Notification::fake();
    Log::spy();

    [$normalized, $batch] = storeWebhookBatch(webhookPayload());

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect(app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']))->not->toBeNull();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => $message === 'Plex webhook batch flush skipped: early job');
});

it('skips a stale job when a newer version exists', function () {
    Notification::fake();
    Log::spy();

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('episode', [
        'title' => 'Episode One',
        'ratingKey' => 'episode-1',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 1,
    ]));

    [, $newerBatch] = storeWebhookBatch(webhookPayload('episode', [
        'title' => 'Episode Two',
        'ratingKey' => 'episode-2',
        'grandparentRatingKey' => 'show-1',
        'grandparentTitle' => 'Lost',
        'parentIndex' => 1,
        'index' => 2,
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    $storedBatch = app(PlexWebhookBatchStore::class)->get($normalized['server_uuid'], $normalized['group_key']);

    expect($storedBatch['version'])->toBe($newerBatch['version']);
    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => $message === 'Plex webhook batch flush skipped: stale job');
});

it('auto-fulfills pending movie request items using Plex metadata enrichment', function () {
    Notification::fake();
    Http::fake([
        'http://plex.example.com:32400/library/metadata/movie-100' => Http::response([
            'MediaContainer' => [
                'Metadata' => [[
                    'title' => 'Inception',
                    'year' => 2010,
                    'Guid' => [
                        ['id' => 'tmdb://27205'],
                        ['id' => 'imdb://tt1375666'],
                    ],
                ]],
            ],
        ]),
    ]);

    PlexMediaServer::factory()->create([
        'client_identifier' => 'server-123',
        'uri' => 'http://plex.example.com:32400',
        'access_token' => 'server-token',
    ]);

    $movie = Movie::factory()->create([
        'title' => 'Inception',
        'year' => 2010,
        'tmdb_id' => 27205,
        'imdb_id' => 'tt1375666',
    ]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('movie', [
        'title' => 'Inception',
        'year' => 2010,
        'ratingKey' => 'movie-100',
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled)
        ->and($item->fresh()->actioned_at)->not->toBeNull();
});

it('auto-fulfills pending episode request items using grandparent metadata enrichment', function () {
    Notification::fake();
    Http::fake([
        'http://plex.example.com:32400/library/metadata/episode-100' => Http::response([
            'MediaContainer' => [
                'Metadata' => [[
                    'title' => 'One Minute',
                    'parentIndex' => 3,
                    'index' => 7,
                ]],
            ],
        ]),
        'http://plex.example.com:32400/library/metadata/show-500' => Http::response([
            'MediaContainer' => [
                'Metadata' => [[
                    'title' => 'Breaking Bad',
                    'Guid' => [
                        ['id' => 'tmdb://1396'],
                        ['id' => 'imdb://tt0903747'],
                    ],
                ]],
            ],
        ]),
    ]);

    PlexMediaServer::factory()->create([
        'client_identifier' => 'server-123',
        'uri' => 'http://plex.example.com:32400',
        'access_token' => 'server-token',
    ]);

    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'tmdb_id' => 1396,
        'imdb_id' => 'tt0903747',
    ]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 3,
        'number' => 7,
    ]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($episode)->create(['request_id' => $request->id]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('episode', [
        'title' => 'One Minute',
        'ratingKey' => 'episode-100',
        'grandparentRatingKey' => 'show-500',
        'grandparentTitle' => 'Breaking Bad',
        'parentIndex' => 3,
        'index' => 7,
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
});

it('falls back to title matching when the source server is missing', function () {
    Notification::fake();
    Log::spy();

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('movie', [
        'title' => 'Inception',
        'year' => 2010,
        'ratingKey' => 'movie-100',
    ], [
        'uuid' => 'missing-server',
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'Plex metadata enrichment failed: source server missing');
});

it('logs metadata enrichment failures and falls back to title matching', function () {
    Notification::fake();
    Log::spy();
    Http::fake([
        'http://plex.example.com:32400/library/metadata/movie-100' => Http::response(status: 500),
    ]);

    PlexMediaServer::factory()->create([
        'client_identifier' => 'server-123',
        'uri' => 'http://plex.example.com:32400',
        'access_token' => 'server-token',
    ]);

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('movie', [
        'title' => 'Inception',
        'year' => 2010,
        'ratingKey' => 'movie-100',
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'Plex metadata enrichment failed');
});

it('skips notification when slack is disabled', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => false]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload());

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Slack is not enabled'));
});

it('skips notification when slack channel is not configured', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.notifications.channel' => null]);

    [$normalized, $batch] = storeWebhookBatch(webhookPayload());

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'channel not configured'));
});

it('does not fulfill already fulfilled request items', function () {
    Notification::fake();

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->fulfilled()->forRequestable($movie)->create(['request_id' => $request->id]);
    $originalActionedAt = $item->actioned_at;

    [$normalized, $batch] = storeWebhookBatch(webhookPayload('movie', [
        'title' => 'Inception',
        'year' => 2010,
        'ratingKey' => 'movie-100',
    ]));

    $this->travel(31)->seconds();

    (new ProcessPlexWebhookBatch(
        serverUuid: $normalized['server_uuid'],
        groupKey: $normalized['group_key'],
        version: $batch['version'],
    ))->handle(app(PlexWebhookBatchStore::class), app(\App\Services\ThirdParty\PlexService::class));

    expect($item->fresh()->actioned_at->timestamp)->toBe($originalActionedAt->timestamp);
});
