<?php

use App\Jobs\ProcessPlexWebhookBatch;
use App\Models\PlexWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.plex.webhook_secret' => 'test-secret']);
    config(['services.plex.webhook_debounce_seconds' => 30]);
});

function plexPayload(string $event = 'library.new', string $type = 'movie', array $metadata = [], array $server = []): string
{
    return json_encode([
        'event' => $event,
        'Metadata' => array_merge([
            'type' => $type,
            'title' => 'Test Movie',
            'year' => 2024,
        ], $metadata),
        'Server' => array_merge([
            'uuid' => 'server-uuid-123',
            'title' => 'My Plex Server',
        ], $server),
    ]);
}

it('stores a movie event and dispatches debounce job', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload(),
    ])->assertOk();

    expect(PlexWebhookEvent::count())->toBe(1);

    $event = PlexWebhookEvent::first();
    expect($event->server_uuid)->toBe('server-uuid-123')
        ->and($event->server_name)->toBe('My Plex Server')
        ->and($event->media_type)->toBe('movie')
        ->and($event->title)->toBe('Test Movie')
        ->and($event->year)->toBe(2024)
        ->and($event->show_title)->toBeNull()
        ->and($event->season)->toBeNull()
        ->and($event->episode_number)->toBeNull()
        ->and($event->processed_at)->toBeNull();

    Queue::assertPushed(ProcessPlexWebhookBatch::class, function ($job) {
        return $job->serverUuid === 'server-uuid-123';
    });
});

it('stores an episode event with correct fields', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'episode', [
            'title' => 'The One Where They All Find Out',
            'grandparentTitle' => 'Friends',
            'parentIndex' => 5,
            'index' => 14,
        ]),
    ])->assertOk();

    $event = PlexWebhookEvent::first();
    expect($event->media_type)->toBe('episode')
        ->and($event->title)->toBe('The One Where They All Find Out')
        ->and($event->show_title)->toBe('Friends')
        ->and($event->season)->toBe(5)
        ->and($event->episode_number)->toBe(14)
        ->and($event->year)->toBeNull();
});

it('rejects invalid tokens', function () {
    $this->post('/api/webhooks/plex/wrong-token', [
        'payload' => plexPayload(),
    ])->assertForbidden();

    expect(PlexWebhookEvent::count())->toBe(0);
});

it('ignores non-library.new events', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('media.play'),
    ])->assertOk();

    expect(PlexWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('ignores unsupported media types', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => plexPayload('library.new', 'track'),
    ])->assertOk();

    expect(PlexWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('deduplicates identical events within 60 seconds', function () {
    Queue::fake();

    $payload = plexPayload();

    $this->post('/api/webhooks/plex/test-secret', ['payload' => $payload])->assertOk();
    $this->post('/api/webhooks/plex/test-secret', ['payload' => $payload])->assertOk();

    expect(PlexWebhookEvent::count())->toBe(1);
});

it('handles malformed payload gracefully', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret', [
        'payload' => 'not-json',
    ])->assertOk();

    expect(PlexWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('handles missing payload gracefully', function () {
    Queue::fake();

    $this->post('/api/webhooks/plex/test-secret')->assertOk();

    expect(PlexWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});
