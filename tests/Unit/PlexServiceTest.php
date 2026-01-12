<?php

use App\Services\PlexService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Generate a test ED25519 key pair
    $keypair = sodium_crypto_sign_keypair();
    $privateKey = sodium_crypto_sign_secretkey($keypair);

    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
        'services.plex.private_key' => base64_encode($privateKey),
        'services.plex.key_id' => 'test-key-id',
    ]);
});

test('createPin returns id and code from Plex API with JWK', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins' => Http::response([
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
        return $request->url() === 'https://clients.plex.tv/api/v2/pins'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Plex-Client-Identifier', 'test-client-id')
            && isset($request->data()['jwk'])
            && $request->data()['jwk']['kty'] === 'OKP'
            && $request->data()['jwk']['crv'] === 'Ed25519';
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

test('getAuthToken exchanges PIN for JWT token with signed device JWT', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins/*' => Http::response([
            'id' => 12345,
            'code' => 'ABCD1234',
            'authToken' => 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.test-jwt-token',
        ]),
    ]);

    $service = new PlexService;
    $token = $service->getAuthToken(12345);

    expect($token)->toBe('eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.test-jwt-token');

    Http::assertSent(function ($request) {
        // Should include deviceJWT query parameter
        return str_contains($request->url(), 'deviceJWT=');
    });
});

test('getAuthToken returns null when PIN is not yet claimed', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins/*' => Http::response([
            'id' => 12345,
            'code' => 'ABCD1234',
            'authToken' => null,
        ]),
    ]);

    $service = new PlexService;
    $token = $service->getAuthToken(12345);

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
    $user = $service->getUserInfo('valid-jwt-token');

    expect($user)->toBe([
        'id' => 98765,
        'uuid' => 'user-uuid-abc',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'thumb' => 'https://plex.tv/users/avatar.jpg',
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Plex-Token', 'valid-jwt-token');
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

test('refreshToken exchanges nonce for new JWT token', function () {
    Http::fake([
        'clients.plex.tv/api/v2/auth/nonce' => Http::response([
            'nonce' => 'test-nonce-12345',
        ]),
        'clients.plex.tv/api/v2/auth/token' => Http::response([
            'auth_token' => 'eyJhbGciOiJFZERTQSJ9.new-refreshed-token',
        ]),
    ]);

    $service = new PlexService;
    $token = $service->refreshToken();

    expect($token)->toBe('eyJhbGciOiJFZERTQSJ9.new-refreshed-token');

    Http::assertSentCount(2);
});
