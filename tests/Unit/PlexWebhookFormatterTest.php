<?php

use App\Models\PlexWebhookEvent;
use App\Support\PlexWebhookFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->formatter = new PlexWebhookFormatter;
    $this->serverUuid = 'server-123';
});

it('formats a single movie', function () {
    $events = collect([
        PlexWebhookEvent::factory()->movie('Inception', 2010)->create([
            'server_uuid' => $this->serverUuid,
            'server_name' => 'My Server',
        ]),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nInception (2010)");
});

it('formats a movie without year', function () {
    $events = collect([
        PlexWebhookEvent::factory()->movie('Unknown Movie', null)->create([
            'server_uuid' => $this->serverUuid,
            'server_name' => 'My Server',
        ]),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nUnknown Movie");
});

it('formats multiple movies sorted by title', function () {
    $events = collect([
        PlexWebhookEvent::factory()->movie('The Matrix', 1999)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->movie('Inception', 2010)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nInception (2010)\nThe Matrix (1999)");
});

it('formats a single episode', function () {
    $events = collect([
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 5, 'Gray Matter')->create([
            'server_uuid' => $this->serverUuid,
            'server_name' => 'My Server',
        ]),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E05");
});

it('formats consecutive episodes as a run', function () {
    $events = collect([
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 2)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 3)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E01-E03");
});

it('formats non-consecutive episodes with gap detection', function () {
    $events = collect([
        PlexWebhookEvent::factory()->episode('Friends', 1, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Friends', 1, 3)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Friends', 1, 4)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Friends', 1, 5)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nFriends S01E01, S01E03-E05");
});

it('formats episodes across multiple seasons', function () {
    $events = collect([
        PlexWebhookEvent::factory()->episode('Lost', 1, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Lost', 1, 2)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Lost', 2, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nLost S01E01-E02, S02E01");
});

it('formats multiple shows each on their own line', function () {
    $events = collect([
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Lost', 2, 5)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E01\nLost S02E05");
});

it('formats mixed movies and episodes', function () {
    $events = collect([
        PlexWebhookEvent::factory()->movie('Inception', 2010)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 1)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
        PlexWebhookEvent::factory()->episode('Breaking Bad', 1, 2)->create(['server_uuid' => $this->serverUuid, 'server_name' => 'My Server']),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toBe("*New on My Server:*\nInception (2010)\nBreaking Bad S01E01-E02");
});

it('uses Plex as fallback server name', function () {
    $events = collect([
        PlexWebhookEvent::factory()->movie('Inception', 2010)->create([
            'server_uuid' => $this->serverUuid,
            'server_name' => null,
        ]),
    ]);

    $result = $this->formatter->format($events);

    expect($result)->toStartWith('*New on Plex:*');
});
