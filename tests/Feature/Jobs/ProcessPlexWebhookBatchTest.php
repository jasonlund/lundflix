<?php

use App\Enums\RequestItemStatus;
use App\Jobs\ProcessPlexWebhookBatch;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.slack.enabled' => true,
        'services.slack.notifications.channel' => 'C12345',
        'services.plex.webhook_debounce_seconds' => 30,
    ]);
});

function cacheBatch(string $serverUuid, array $items, ?string $serverName = 'My Server', ?int $lastReceivedAt = null): void
{
    Cache::put("plex-webhook:{$serverUuid}", [
        'server_name' => $serverName,
        'items' => $items,
        'last_received_at' => $lastReceivedAt ?? now()->subSeconds(60)->timestamp,
    ], now()->addHours(4));
}

it('processes batch and sends notification when debounce window has elapsed', function () {
    Notification::fake();

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(Cache::get('plex-webhook:server-123'))->toBeNull();
    Notification::assertSentOnDemand(PlexLibraryNotification::class);
});

it('releases back to queue when events are still within debounce window', function () {
    Notification::fake();

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ], lastReceivedAt: now()->subSeconds(5)->timestamp);

    $job = (new ProcessPlexWebhookBatch('server-123'))->withFakeQueueInteractions();

    $job->handle();

    expect(Cache::get('plex-webhook:server-123'))->not->toBeNull();
    $job->assertReleased(delay: 30);
    Notification::assertNothingSent();
});

it('does nothing when cache is empty', function () {
    Notification::fake();

    (new ProcessPlexWebhookBatch('nonexistent-server'))->handle();

    Notification::assertNothingSent();
});

it('does not mix batches from different servers', function () {
    Notification::fake();

    cacheBatch('server-a', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    cacheBatch('server-b', [
        ['media_type' => 'movie', 'title' => 'The Matrix', 'year' => 1999, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-a'))->handle();

    expect(Cache::get('plex-webhook:server-a'))->toBeNull()
        ->and(Cache::get('plex-webhook:server-b'))->not->toBeNull();
});

it('skips notification when slack is disabled', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => false]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(Cache::get('plex-webhook:server-123'))->toBeNull();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Slack is not enabled'));
});

it('skips notification when slack channel is not configured', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.notifications.channel' => null]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(Cache::get('plex-webhook:server-123'))->toBeNull();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'channel not configured'));
});

it('auto-fulfills pending movie request items matching webhook content', function () {
    Notification::fake();

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled)
        ->and($item->fresh()->actioned_at)->not->toBeNull();
});

it('auto-fulfills pending episode request items matching webhook content', function () {
    Notification::fake();

    $show = Show::factory()->create(['name' => 'Breaking Bad']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 3, 'number' => 7]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($episode)->create(['request_id' => $request->id]);

    cacheBatch('server-123', [
        ['media_type' => 'episode', 'title' => 'One Minute', 'year' => null, 'show_title' => 'Breaking Bad', 'season' => 3, 'episode_number' => 7],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
});

it('does not fulfill already fulfilled request items', function () {
    Notification::fake();

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->fulfilled()->forRequestable($movie)->create(['request_id' => $request->id]);
    $originalActionedAt = $item->actioned_at;

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect($item->fresh()->actioned_at->timestamp)->toBe($originalActionedAt->timestamp);
});

it('does not dispatch RequestFulfilled event on auto-fulfillment', function () {
    Notification::fake();
    Event::fake([\App\Events\RequestFulfilled::class]);

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    Event::assertNotDispatched(\App\Events\RequestFulfilled::class);
});

it('still auto-fulfills requests when slack is disabled', function () {
    Notification::fake();
    config(['services.slack.enabled' => false]);

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect($item->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
});
