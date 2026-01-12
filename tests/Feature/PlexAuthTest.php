<?php

use App\Models\User;
use App\Services\PlexService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('redirects to plex for authentication', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins' => Http::response([
            'id' => 12345,
            'code' => 'ABC123',
        ]),
    ]);

    $response = $this->get('/auth/plex');

    $response->assertRedirect();
    $response->assertRedirectContains('app.plex.tv/auth');
    $response->assertRedirectContains('ABC123');

    expect(session('plex_pin_id'))->toBe(12345);
});

it('creates a new user on successful plex authentication', function () {
    $this->freezeSecond(function () {
        $this->withSession(['plex_pin_id' => 12345]);

        Http::fake([
            'clients.plex.tv/api/v2/pins/12345' => Http::response([
                'authToken' => 'pin-claimed',
            ]),
            'clients.plex.tv/api/v2/auth/nonce' => Http::response([
                'nonce' => 'test-nonce-uuid',
            ]),
            'clients.plex.tv/api/v2/auth/token' => Http::response([
                'auth_token' => 'test-jwt-token',
            ]),
            'plex.tv/api/v2/user' => Http::response([
                'id' => 999,
                'uuid' => 'user-uuid-123',
                'username' => 'plexuser',
                'email' => 'plexuser@example.com',
                'thumb' => 'https://plex.tv/users/999/avatar',
            ]),
            'clients.plex.tv/api/v2/resources*' => Http::response([
                [
                    'clientIdentifier' => config('services.plex.server_identifier'),
                    'provides' => 'server',
                ],
            ]),
        ]);

        $response = $this->get('/auth/plex/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('plex_id', '999')->first();
        expect($user)->not->toBeNull()
            ->and($user->plex_username)->toBe('plexuser')
            ->and($user->email)->toBe('plexuser@example.com')
            ->and($user->name)->toBe('plexuser')
            ->and($user->plex_token_expires_at)->toEqual(now()->addDays(PlexService::TOKEN_LIFETIME_DAYS));
    });
});

it('logs in an existing plex user', function () {
    $existingUser = User::factory()->withPlex()->create([
        'plex_id' => '999',
        'name' => 'Existing User',
    ]);

    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'pin-claimed',
        ]),
        'clients.plex.tv/api/v2/auth/nonce' => Http::response([
            'nonce' => 'test-nonce-uuid',
        ]),
        'clients.plex.tv/api/v2/auth/token' => Http::response([
            'auth_token' => 'new-jwt-token',
        ]),
        'plex.tv/api/v2/user' => Http::response([
            'id' => 999,
            'uuid' => 'user-uuid-123',
            'username' => 'updated-username',
            'email' => 'plexuser@example.com',
            'thumb' => 'https://plex.tv/users/999/avatar',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'clientIdentifier' => config('services.plex.server_identifier'),
                'provides' => 'server',
            ],
        ]),
    ]);

    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($existingUser);

    expect(User::count())->toBe(1);
    expect($existingUser->fresh()->plex_username)->toBe('updated-username');
    expect($existingUser->fresh()->plex_token_expires_at)->not->toBeNull();
});

it('rejects users without server access', function () {
    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'pin-claimed',
        ]),
        'clients.plex.tv/api/v2/auth/nonce' => Http::response([
            'nonce' => 'test-nonce-uuid',
        ]),
        'clients.plex.tv/api/v2/auth/token' => Http::response([
            'auth_token' => 'test-jwt-token',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'clientIdentifier' => 'different-server-id',
                'provides' => 'server',
            ],
        ]),
    ]);

    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'You do not have access to this server.');
    $this->assertGuest();
});

it('handles missing pin session', function () {
    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Invalid authentication session.');
    $this->assertGuest();
});

it('handles unclaimed pin', function () {
    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => null,
        ]),
    ]);

    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Authentication failed. Please try again.');
    $this->assertGuest();
});

it('refreshes token on 498 expired response', function () {
    $user = User::factory()->withPlex()->create([
        'plex_token' => 'old-expired-token',
        'plex_token_expires_at' => now()->subDay(),
    ]);

    Http::fake([
        // First request fails with 498
        'plex.tv/api/v2/user' => Http::sequence()
            ->push(null, 498)
            ->push([
                'id' => $user->plex_id,
                'uuid' => 'user-uuid',
                'username' => 'plexuser',
                'email' => 'test@example.com',
                'thumb' => null,
            ]),
        // Token refresh endpoints
        'clients.plex.tv/api/v2/auth/nonce' => Http::response([
            'nonce' => 'refresh-nonce',
        ]),
        'clients.plex.tv/api/v2/auth/token' => Http::response([
            'auth_token' => 'new-refreshed-token',
        ]),
    ]);

    $plex = app(PlexService::class);
    $response = $plex->authenticatedRequest($user, 'get', 'https://plex.tv/api/v2/user');

    expect($response->successful())->toBeTrue();
    expect($user->fresh()->plex_token)->toBe('new-refreshed-token');
    expect($user->fresh()->plex_token_expires_at->isFuture())->toBeTrue();
});
