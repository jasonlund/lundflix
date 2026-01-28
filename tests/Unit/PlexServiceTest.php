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

test('getOnlineServers returns only online servers with non-local URIs', function () {
    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Online Server',
                'clientIdentifier' => 'server-online',
                'accessToken' => 'token-online',
                'owned' => true,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://192.168.1.1:32400', 'local' => true],
                    ['uri' => 'http://server.example.com:32400', 'local' => false],
                ],
            ],
            [
                'name' => 'Offline Server',
                'clientIdentifier' => 'server-offline',
                'accessToken' => 'token-offline',
                'owned' => true,
                'provides' => 'server',
                'presence' => false,
                'connections' => [
                    ['uri' => 'http://offline.example.com:32400', 'local' => false],
                ],
            ],
            [
                'name' => 'Player Device',
                'clientIdentifier' => 'player-1',
                'accessToken' => 'token-player',
                'owned' => true,
                'provides' => 'player',
                'presence' => true,
                'connections' => [],
            ],
        ]),
    ]);

    $service = new PlexService;
    $servers = $service->getOnlineServers('valid-token');

    expect($servers)->toHaveCount(1)
        ->and($servers->first())->toBe([
            'name' => 'Online Server',
            'clientIdentifier' => 'server-online',
            'accessToken' => 'token-online',
            'owned' => true,
            'uri' => 'http://server.example.com:32400',
        ]);
});

test('resolvePlexGuid returns Plex GUID for valid external ID', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'size' => 1,
                'Metadata' => [
                    [
                        'guid' => 'plex://movie/5d77685333f255001e852e11',
                        'title' => 'Inception',
                    ],
                ],
            ],
        ]),
    ]);

    $service = new PlexService;
    $guid = $service->resolvePlexGuid('valid-token', 'imdb://tt1375666', 1);

    expect($guid)->toBe('plex://movie/5d77685333f255001e852e11');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'metadata.provider.plex.tv')
            && $request->data()['type'] === 1
            && $request->data()['guid'] === 'imdb://tt1375666';
    });
});

test('resolvePlexGuid returns null when external ID not found', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'size' => 0,
            ],
        ]),
    ]);

    $service = new PlexService;
    $guid = $service->resolvePlexGuid('valid-token', 'imdb://tt9999999', 1);

    expect($guid)->toBeNull();
});

test('searchByExternalId returns servers that have the content', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    ['guid' => 'plex://movie/abc123'],
                ],
            ],
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Server A',
                'clientIdentifier' => 'server-a',
                'accessToken' => 'token-a',
                'owned' => true,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://server-a.example.com:32400', 'local' => false],
                ],
            ],
            [
                'name' => 'Server B',
                'clientIdentifier' => 'server-b',
                'accessToken' => 'token-b',
                'owned' => false,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://server-b.example.com:32400', 'local' => false],
                ],
            ],
        ]),
        'http://server-a.example.com:32400/library/all*' => Http::response([
            'MediaContainer' => [
                'size' => 0,
            ],
        ]),
        'http://server-b.example.com:32400/library/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    ['title' => 'Inception', 'year' => 2010, 'ratingKey' => '12345'],
                ],
            ],
        ]),
    ]);

    $service = new PlexService;
    $results = $service->searchByExternalId('valid-token', 'imdb://tt1375666', 1);

    expect($results)->toHaveCount(1)
        ->and($results->first()['name'])->toBe('Server B')
        ->and($results->first()['match']['title'])->toBe('Inception');
});

test('searchByExternalId returns empty collection when external ID cannot be resolved', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'size' => 0,
            ],
        ]),
    ]);

    $service = new PlexService;
    $results = $service->searchByExternalId('valid-token', 'imdb://tt9999999', 1);

    expect($results)->toBeEmpty();
});

test('searchShowWithEpisodes returns servers with episode lists', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    ['guid' => 'plex://show/abc123'],
                ],
            ],
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Test Server',
                'clientIdentifier' => 'server-test',
                'accessToken' => 'token-test',
                'owned' => true,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://test.example.com:32400', 'local' => false],
                ],
            ],
        ]),
        'http://test.example.com:32400/library/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    ['title' => 'The Chair Company', 'year' => 2025, 'ratingKey' => '12345'],
                ],
            ],
        ]),
        'http://test.example.com:32400/library/metadata/12345/allLeaves' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    ['parentIndex' => 1, 'index' => 1, 'title' => 'Episode One'],
                    ['parentIndex' => 1, 'index' => 2, 'title' => 'Episode Two'],
                ],
            ],
        ]),
    ]);

    $service = new PlexService;
    $results = $service->searchShowWithEpisodes('valid-token', 'imdb://tt31987295');

    expect($results)->toHaveCount(1)
        ->and($results->first()['name'])->toBe('Test Server')
        ->and($results->first()['show']['title'])->toBe('The Chair Company')
        ->and($results->first()['episodes'])->toHaveCount(2)
        ->and($results->first()['episodes'][0])->toBe([
            'season' => 1,
            'episode' => 1,
            'title' => 'Episode One',
        ]);
});

test('searchShowWithEpisodes returns empty collection when show not found', function () {
    Http::fake([
        'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
            'MediaContainer' => [
                'size' => 0,
            ],
        ]),
    ]);

    $service = new PlexService;
    $results = $service->searchShowWithEpisodes('valid-token', 'imdb://tt9999999');

    expect($results)->toBeEmpty();
});
