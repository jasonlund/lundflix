<?php

use App\Models\PlexMediaServer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be created with factory', function () {
    $server = PlexMediaServer::factory()->create();

    expect($server)->toBeInstanceOf(PlexMediaServer::class)
        ->and($server->name)->toBeString()
        ->and($server->client_identifier)->toBeString()
        ->and($server->is_online)->toBeTrue();
});

it('encrypts the access token', function () {
    $server = PlexMediaServer::factory()->create([
        'access_token' => 'my-secret-token',
    ]);

    // Reload from database
    $server->refresh();

    // The decrypted value should match
    expect($server->access_token)->toBe('my-secret-token');

    // But the raw database value should be encrypted (different from plaintext)
    $rawValue = \DB::table('plex_media_servers')
        ->where('id', $server->id)
        ->value('access_token');

    expect($rawValue)->not->toBe('my-secret-token');
});

it('casts connections as array', function () {
    $connections = [
        ['uri' => 'http://192.168.1.1:32400', 'local' => true],
        ['uri' => 'http://example.com:32400', 'local' => false],
    ];

    $server = PlexMediaServer::factory()->create([
        'connections' => $connections,
    ]);

    $server->refresh();

    expect($server->connections)->toBeArray()
        ->and($server->connections)->toHaveCount(2)
        ->and($server->connections[0]['local'])->toBeTrue();
});

it('casts last_seen_at as datetime', function () {
    $server = PlexMediaServer::factory()->create();

    expect($server->last_seen_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('has offline factory state', function () {
    $server = PlexMediaServer::factory()->offline()->create();

    expect($server->is_online)->toBeFalse()
        ->and($server->last_seen_at->lt(now()->subHour()))->toBeTrue();
});

it('returns the plex web app url', function () {
    $server = PlexMediaServer::factory()->create([
        'client_identifier' => 'abc123',
    ]);

    expect($server->webUrl())->toBe(
        'https://app.plex.tv/desktop/#!/media/abc123/com.plexapp.plugins.library?key=%2Fhubs&pageType=hub'
    );
});

it('casts plex_last_seen_at as datetime', function () {
    $server = PlexMediaServer::factory()->create();

    expect($server->plex_last_seen_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('stores plex resource fields', function () {
    $server = PlexMediaServer::factory()->create([
        'source_title' => 'jgamboa',
        'owner_thumb' => 'https://plex.tv/users/12345/avatar',
        'owner_id' => '12345',
        'product_version' => '1.41.2.9200',
        'platform' => 'Linux',
        'platform_version' => '22.04',
    ]);

    $server->refresh();

    expect($server->source_title)->toBe('jgamboa')
        ->and($server->owner_thumb)->toBe('https://plex.tv/users/12345/avatar')
        ->and($server->owner_id)->toBe('12345')
        ->and($server->product_version)->toBe('1.41.2.9200')
        ->and($server->platform)->toBe('Linux')
        ->and($server->platform_version)->toBe('22.04');
});

it('allows nullable plex resource fields', function () {
    $server = PlexMediaServer::factory()->create([
        'source_title' => null,
        'owner_thumb' => null,
        'owner_id' => null,
        'product_version' => null,
        'platform' => null,
        'platform_version' => null,
        'plex_last_seen_at' => null,
    ]);

    $server->refresh();

    expect($server->source_title)->toBeNull()
        ->and($server->owner_thumb)->toBeNull()
        ->and($server->owner_id)->toBeNull()
        ->and($server->product_version)->toBeNull()
        ->and($server->platform)->toBeNull()
        ->and($server->platform_version)->toBeNull()
        ->and($server->plex_last_seen_at)->toBeNull();
});

it('has unique client identifier', function () {
    $server1 = PlexMediaServer::factory()->create([
        'client_identifier' => 'unique-id-123',
    ]);

    expect(fn () => PlexMediaServer::factory()->create([
        'client_identifier' => 'unique-id-123',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
