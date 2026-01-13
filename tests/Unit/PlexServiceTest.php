<?php

use App\Services\PlexService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
    ]);
});

test('createPin returns id and code from Plex API', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins?strong=true' => Http::response([
            'id' => 12345,
            'code' => 'ABCD1234',
        ]),
    ]);

    $service = new PlexService;
    $pin = $service->createPin();

    expect($pin)->toBe([
        'id' => 12345,
        'code' => 'ABCD1234',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'clients.plex.tv/api/v2/pins')
            && $request->method() === 'POST'
            && $request->hasHeader('X-Plex-Client-Identifier', 'test-client-id');
    });
});

test('getAuthUrl generates correct Plex auth URL', function () {
    $service = new PlexService;
    $url = $service->getAuthUrl('ABCD1234', 'https://example.com/callback');

    expect($url)->toStartWith('https://app.plex.tv/auth#?')
        ->and($url)->toContain('clientID=test-client-id')
        ->and($url)->toContain('code=ABCD1234')
        ->and($url)->toContain(urlencode('https://example.com/callback'));
});

test('getTokenFromPin returns token when PIN is claimed', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'id' => 12345,
            'code' => 'ABCD1234',
            'authToken' => 'test-plex-token',
        ]),
    ]);

    $service = new PlexService;
    $token = $service->getTokenFromPin(12345);

    expect($token)->toBe('test-plex-token');
});

test('getTokenFromPin returns null when PIN is not yet claimed', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'id' => 12345,
            'code' => 'ABCD1234',
            'authToken' => null,
        ]),
    ]);

    $service = new PlexService;
    $token = $service->getTokenFromPin(12345);

    expect($token)->toBeNull();
});

test('getUserInfo returns user details from Plex', function () {
    Http::fake([
        'plex.tv/api/v2/user' => Http::response([
            'id' => 98765,
            'uuid' => 'user-uuid-abc',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'thumb' => 'https://plex.tv/users/avatar.jpg',
        ]),
    ]);

    $service = new PlexService;
    $user = $service->getUserInfo('valid-token');

    expect($user)->toBe([
        'id' => 98765,
        'uuid' => 'user-uuid-abc',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'thumb' => 'https://plex.tv/users/avatar.jpg',
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Plex-Token', 'valid-token');
    });
});

test('getUserResources returns collection of resources', function () {
    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'My Server',
                'clientIdentifier' => 'server-abc',
                'provides' => 'server',
            ],
            [
                'name' => 'Plex Web',
                'clientIdentifier' => 'player-xyz',
                'provides' => 'player',
            ],
        ]),
    ]);

    $service = new PlexService;
    $resources = $service->getUserResources('valid-token');

    expect($resources)->toHaveCount(2)
        ->and($resources->first()['name'])->toBe('My Server');
});

test('hasServerAccess returns true when user has access to configured server', function () {
    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Other Server',
                'clientIdentifier' => 'other-server',
                'provides' => 'server',
            ],
            [
                'name' => 'Our Server',
                'clientIdentifier' => 'test-server-123',
                'provides' => 'server',
            ],
        ]),
    ]);

    $service = new PlexService;

    expect($service->hasServerAccess('valid-token'))->toBeTrue();
});

test('hasServerAccess returns false when user lacks access to configured server', function () {
    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Other Server',
                'clientIdentifier' => 'other-server',
                'provides' => 'server',
            ],
        ]),
    ]);

    $service = new PlexService;

    expect($service->hasServerAccess('valid-token'))->toBeFalse();
});

test('hasServerAccess returns false for matching identifier that is not a server', function () {
    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Player with same ID',
                'clientIdentifier' => 'test-server-123',
                'provides' => 'player',
            ],
        ]),
    ]);

    $service = new PlexService;

    expect($service->hasServerAccess('valid-token'))->toBeFalse();
});
