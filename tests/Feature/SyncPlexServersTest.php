<?php

use App\Models\PlexMediaServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
        'services.plex.seed_token' => 'admin-token',
    ]);
});

it('uses admin thumb for owned servers and friend thumbs for shared servers', function () {
    Http::fake([
        'plex.tv/api/v2/user' => Http::response([
            'id' => 217658,
            'uuid' => '6e1e991aa79f07da',
            'username' => 'jasonlund',
            'email' => 'jasonlund@gmail.com',
            'thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1771730971',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'lundflix',
                'clientIdentifier' => 'server-owned',
                'accessToken' => 'token-owned',
                'provides' => 'server',
                'owned' => true,
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://owned.example.com:32400', 'local' => false],
                ],
            ],
            [
                'name' => 'gamboaplex',
                'clientIdentifier' => 'server-shared',
                'accessToken' => 'token-shared',
                'provides' => 'server',
                'owned' => false,
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://shared.example.com:32400', 'local' => false],
                ],
                'sourceTitle' => 'jgamboa',
                'ownerId' => 3900595,
            ],
        ]),
        'clients.plex.tv/api/v2/friends*' => Http::response([
            [
                'id' => 3900595,
                'uuid' => 'dc2101cf70149f3c',
                'title' => 'jgamboa',
                'username' => 'jgamboa',
                'thumb' => 'https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676',
            ],
        ]),
    ]);

    $this->artisan('plex:sync-servers')->assertSuccessful();

    $owned = PlexMediaServer::where('client_identifier', 'server-owned')->first();
    $shared = PlexMediaServer::where('client_identifier', 'server-shared')->first();

    expect($owned->owner_thumb)->toBe('https://plex.tv/users/6e1e991aa79f07da/avatar?c=1771730971')
        ->and($shared->owner_thumb)->toBe('https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676');
});

it('stores plex resource fields during sync', function () {
    Http::fake([
        'plex.tv/api/v2/user' => Http::response([
            'id' => 217658,
            'uuid' => '6e1e991aa79f07da',
            'username' => 'jasonlund',
            'email' => 'jasonlund@gmail.com',
            'thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1771730971',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'gamboaplex',
                'clientIdentifier' => 'server-abc',
                'accessToken' => 'token-abc',
                'provides' => 'server',
                'owned' => false,
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://server.example.com:32400', 'local' => false],
                ],
                'sourceTitle' => 'jgamboa',
                'ownerId' => 12345,
                'productVersion' => '1.41.2.9200',
                'platform' => 'Linux',
                'platformVersion' => '22.04',
                'lastSeenAt' => '2026-02-21T12:00:00Z',
            ],
        ]),
        'clients.plex.tv/api/v2/friends*' => Http::response([
            [
                'id' => 12345,
                'uuid' => 'dc2101cf70149f3c',
                'title' => 'jgamboa',
                'username' => 'jgamboa',
                'thumb' => 'https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676',
            ],
        ]),
    ]);

    $this->artisan('plex:sync-servers')->assertSuccessful();

    $server = PlexMediaServer::where('client_identifier', 'server-abc')->first();

    expect($server->source_title)->toBe('jgamboa')
        ->and($server->owner_thumb)->toBe('https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676')
        ->and($server->owner_id)->toBe('12345')
        ->and($server->product_version)->toBe('1.41.2.9200')
        ->and($server->platform)->toBe('Linux')
        ->and($server->platform_version)->toBe('22.04')
        ->and($server->plex_last_seen_at)->not->toBeNull();
});

it('handles missing plex resource fields gracefully', function () {
    Http::fake([
        'plex.tv/api/v2/user' => Http::response([
            'id' => 217658,
            'uuid' => '6e1e991aa79f07da',
            'username' => 'jasonlund',
            'email' => 'jasonlund@gmail.com',
            'thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1771730971',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Basic Server',
                'clientIdentifier' => 'server-basic',
                'accessToken' => 'token-basic',
                'provides' => 'server',
                'owned' => false,
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://basic.example.com:32400', 'local' => false],
                ],
            ],
        ]),
        'clients.plex.tv/api/v2/friends*' => Http::response([]),
    ]);

    $this->artisan('plex:sync-servers')->assertSuccessful();

    $server = PlexMediaServer::where('client_identifier', 'server-basic')->first();

    expect($server->source_title)->toBeNull()
        ->and($server->owner_thumb)->toBeNull()
        ->and($server->owner_id)->toBeNull()
        ->and($server->product_version)->toBeNull()
        ->and($server->platform)->toBeNull()
        ->and($server->platform_version)->toBeNull()
        ->and($server->plex_last_seen_at)->toBeNull();
});

it('updates plex resource fields on subsequent syncs', function () {
    PlexMediaServer::factory()->create([
        'client_identifier' => 'server-update',
        'product_version' => '1.40.0.0000',
        'platform' => 'Windows',
    ]);

    Http::fake([
        'plex.tv/api/v2/user' => Http::response([
            'id' => 217658,
            'uuid' => '6e1e991aa79f07da',
            'username' => 'jasonlund',
            'email' => 'jasonlund@gmail.com',
            'thumb' => 'https://plex.tv/users/6e1e991aa79f07da/avatar?c=1771730971',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Updated Server',
                'clientIdentifier' => 'server-update',
                'accessToken' => 'token-update',
                'provides' => 'server',
                'owned' => false,
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://update.example.com:32400', 'local' => false],
                ],
                'sourceTitle' => 'newowner',
                'ownerId' => 99999,
                'productVersion' => '1.41.2.9200',
                'platform' => 'Linux',
                'platformVersion' => '24.04',
                'lastSeenAt' => '2026-02-21T15:00:00Z',
            ],
        ]),
        'clients.plex.tv/api/v2/friends*' => Http::response([
            [
                'id' => 99999,
                'uuid' => 'abc123def456',
                'title' => 'newowner',
                'username' => 'newowner',
                'thumb' => 'https://plex.tv/users/abc123def456/avatar?c=1234567890',
            ],
        ]),
    ]);

    $this->artisan('plex:sync-servers')->assertSuccessful();

    $server = PlexMediaServer::where('client_identifier', 'server-update')->first();

    expect($server->product_version)->toBe('1.41.2.9200')
        ->and($server->platform)->toBe('Linux')
        ->and($server->source_title)->toBe('newowner')
        ->and($server->owner_thumb)->toBe('https://plex.tv/users/abc123def456/avatar?c=1234567890');
});
