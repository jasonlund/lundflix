<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('redirects new users to registration', function () {
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

    $response = $this->get('/auth/plex/callback?pin_id=12345');

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

it('redirects existing plex user to login with error', function () {
    $existingUser = User::factory()->withPlex()->create([
        'plex_id' => '999',
        'name' => 'Existing User',
    ]);

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

    $response = $this->get('/auth/plex/callback?pin_id=12345');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.already_linked')]);
    $this->assertGuest();

    expect(User::count())->toBe(1);
});

it('rejects users without server access', function () {
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
                'clientIdentifier' => 'different-server-id',
                'provides' => 'server',
            ],
        ]),
    ]);

    $response = $this->get('/auth/plex/callback?pin_id=12345');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.no_access')]);
    $this->assertGuest();
});

it('handles missing pin_id parameter', function () {
    $response = $this->get('/auth/plex/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.auth_failed')]);
    $this->assertGuest();
});

it('handles non-integer pin_id', function () {
    $response = $this->get('/auth/plex/callback?pin_id=abc');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.auth_failed')]);
    $this->assertGuest();
});

it('handles unclaimed pin', function () {
    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => null,
        ]),
    ]);

    $response = $this->get('/auth/plex/callback?pin_id=12345');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.auth_failed')]);
    $this->assertGuest();
});
