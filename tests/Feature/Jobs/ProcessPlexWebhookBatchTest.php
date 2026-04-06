<?php

use App\Jobs\ProcessPlexWebhookBatch;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Support\Facades\Cache;
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
    ], now()->addHours(1));
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
    config(['services.slack.enabled' => false]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(Cache::get('plex-webhook:server-123'))->toBeNull();
    Notification::assertNothingSent();
});

it('skips notification when slack channel is not configured', function () {
    Notification::fake();
    config(['services.slack.notifications.channel' => null]);

    cacheBatch('server-123', [
        ['media_type' => 'movie', 'title' => 'Inception', 'year' => 2010, 'show_title' => null, 'season' => null, 'episode_number' => null],
    ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(Cache::get('plex-webhook:server-123'))->toBeNull();
    Notification::assertNothingSent();
});
