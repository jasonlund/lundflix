<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('redirects to plex for authentication', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins?strong=true' => Http::response([
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

it('redirects new users to registration', function () {
    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'test-plex-token',
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

    $response->assertRedirect(route('register'));
    $this->assertGuest();

    expect(User::count())->toBe(0);
    expect(session('plex_registration'))->toBe([
        'plex_id' => 999,
        'plex_token' => 'test-plex-token',
        'plex_username' => 'plexuser',
        'plex_email' => 'plexuser@example.com',
        'plex_thumb' => 'https://plex.tv/users/999/avatar',
    ]);
});

it('logs in an existing plex user', function () {
    $existingUser = User::factory()->withPlex()->create([
        'plex_id' => '999',
        'name' => 'Existing User',
    ]);

    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'new-plex-token',
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

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($existingUser);

    expect(User::count())->toBe(1);
    expect($existingUser->fresh()->plex_username)->toBe('updated-username');
});

it('rejects users without server access', function () {
    $this->withSession(['plex_pin_id' => 12345]);

    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'test-plex-token',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'clientIdentifier' => 'different-server-id',
                'provides' => 'server',
            ],
        ]),
    ]);

    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error', 'You do not have access to this server.');
    $this->assertGuest();
});

it('handles missing pin session', function () {
    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('home'));
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

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error', 'Authentication failed. Please try again.');
    $this->assertGuest();
});
