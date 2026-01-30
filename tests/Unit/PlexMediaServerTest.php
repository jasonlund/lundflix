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

it('has unique client identifier', function () {
    $server1 = PlexMediaServer::factory()->create([
        'client_identifier' => 'unique-id-123',
    ]);

    expect(fn () => PlexMediaServer::factory()->create([
        'client_identifier' => 'unique-id-123',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
