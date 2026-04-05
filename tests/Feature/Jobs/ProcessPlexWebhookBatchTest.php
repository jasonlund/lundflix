<?php

use App\Jobs\ProcessPlexWebhookBatch;
use App\Models\PlexWebhookEvent;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.slack.enabled' => true,
        'services.slack.notifications.channel' => 'C12345',
        'services.plex.webhook_debounce_seconds' => 30,
    ]);
});

it('processes batch and sends notification when debounce window has elapsed', function () {
    Notification::fake();

    $serverUuid = 'server-123';

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->create([
            'server_uuid' => $serverUuid,
            'server_name' => 'My Server',
            'created_at' => now()->subSeconds(60),
        ]);

    (new ProcessPlexWebhookBatch($serverUuid))->handle();

    expect(PlexWebhookEvent::first()->processed_at)->not->toBeNull();
    Notification::assertSentOnDemand(PlexLibraryNotification::class);
});

it('releases back to queue when events are still within debounce window', function () {
    Notification::fake();

    $serverUuid = 'server-123';

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->create([
            'server_uuid' => $serverUuid,
            'created_at' => now()->subSeconds(5),
        ]);

    $job = (new ProcessPlexWebhookBatch($serverUuid))->withFakeQueueInteractions();

    $job->handle();

    expect(PlexWebhookEvent::first()->processed_at)->toBeNull();
    $job->assertReleased(delay: 30);
    Notification::assertNothingSent();
});

it('does nothing when no unprocessed events exist', function () {
    Notification::fake();

    (new ProcessPlexWebhookBatch('nonexistent-server'))->handle();

    Notification::assertNothingSent();
});

it('does not mix events from different servers', function () {
    Notification::fake();

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->create([
            'server_uuid' => 'server-a',
            'created_at' => now()->subSeconds(60),
        ]);

    PlexWebhookEvent::factory()
        ->movie('The Matrix', 1999)
        ->create([
            'server_uuid' => 'server-b',
            'created_at' => now()->subSeconds(60),
        ]);

    (new ProcessPlexWebhookBatch('server-a'))->handle();

    $serverA = PlexWebhookEvent::where('server_uuid', 'server-a')->first();
    $serverB = PlexWebhookEvent::where('server_uuid', 'server-b')->first();

    expect($serverA->processed_at)->not->toBeNull()
        ->and($serverB->processed_at)->toBeNull();
});

it('skips notification when slack is disabled', function () {
    Notification::fake();
    config(['services.slack.enabled' => false]);

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->create([
            'server_uuid' => 'server-123',
            'created_at' => now()->subSeconds(60),
        ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(PlexWebhookEvent::first()->processed_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('skips notification when slack channel is not configured', function () {
    Notification::fake();
    config(['services.slack.notifications.channel' => null]);

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->create([
            'server_uuid' => 'server-123',
            'created_at' => now()->subSeconds(60),
        ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    expect(PlexWebhookEvent::first()->processed_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('ignores already processed events', function () {
    Notification::fake();

    PlexWebhookEvent::factory()
        ->movie('Inception', 2010)
        ->processed()
        ->create([
            'server_uuid' => 'server-123',
            'created_at' => now()->subSeconds(60),
        ]);

    (new ProcessPlexWebhookBatch('server-123'))->handle();

    Notification::assertNothingSent();
});
