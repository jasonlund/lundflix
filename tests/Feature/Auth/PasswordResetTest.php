<?php

use App\Models\User;
use App\Services\ThirdParty\PlexService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakePlexResponses(string $plexId = '999'): void
{
    Http::fake([
        'clients.plex.tv/api/v2/pins/12345' => Http::response([
            'authToken' => 'fresh-plex-token',
        ]),
        'plex.tv/api/v2/user' => Http::response([
            'id' => (int) $plexId,
            'uuid' => 'user-uuid-123',
            'username' => 'plexuser',
            'email' => 'plexuser@example.com',
            'thumb' => 'https://plex.tv/users/avatar',
        ]),
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'clientIdentifier' => config('services.plex.server_identifier'),
                'provides' => 'server',
            ],
        ]),
    ]);
}

it('redirects existing user to reset password form after plex auth', function () {
    $user = User::factory()->withPlex()->create(['plex_id' => '999']);

    fakePlexResponses();

    $response = $this->withSession([
        'plex_pin_id' => 12345,
        'plex_intent' => 'password_reset',
    ])->get('/auth/plex/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/reset-password/');
    expect($response->headers->get('Location'))->toContain('email='.urlencode($user->email));
});

it('updates plex token during password reset flow', function () {
    $user = User::factory()->withPlex()->create([
        'plex_id' => '999',
        'plex_token' => 'old-plex-token',
    ]);

    fakePlexResponses();

    $this->withSession([
        'plex_pin_id' => 12345,
        'plex_intent' => 'password_reset',
    ])->get('/auth/plex/callback');

    expect($user->fresh()->plex_token)->toBe('fresh-plex-token');
});

it('rejects password reset when no account exists for plex user', function () {
    fakePlexResponses();

    $response = $this->withSession([
        'plex_pin_id' => 12345,
        'plex_intent' => 'password_reset',
    ])->get('/auth/plex/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['plex' => __('lundbergh.plex.no_account')]);
});

it('renders the reset password form with a valid token', function () {
    $user = User::factory()->withPlex()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->get('/reset-password/'.$token.'?email='.urlencode($user->email));

    $response->assertOk();
    $response->assertSee(__('lundbergh.form.password_reset_verified'));
    $response->assertSee($user->email);
});

it('resets password with a valid token', function () {
    $user = User::factory()->withPlex()->create([
        'password' => 'old-password',
    ]);
    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertRedirect(route('login'));
    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('rejects reset with an invalid token', function () {
    $user = User::factory()->withPlex()->create();

    $response = $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
});

it('sets plex_intent to password_reset via livewire action', function () {
    $mockPlex = Mockery::mock(PlexService::class);
    $mockPlex->shouldReceive('createPin')->once()->andReturn([
        'id' => 98765,
        'code' => 'test-pin-code',
    ]);
    $mockPlex->shouldReceive('getAuthUrl')
        ->once()
        ->with('test-pin-code', route('auth.plex.callback'))
        ->andReturn('https://app.plex.tv/auth#?code=test-pin-code');

    app()->instance(PlexService::class, $mockPlex);

    Livewire::test('auth.login')
        ->call('redirectToPlex', 'password_reset')
        ->assertSessionHas('plex_intent', 'password_reset')
        ->assertSessionHas('plex_pin_id', 98765)
        ->assertRedirect('https://app.plex.tv/auth#?code=test-pin-code');
});

it('preserves register intent for existing registration flow', function () {
    fakePlexResponses();

    $response = $this->withSession([
        'plex_pin_id' => 12345,
    ])->get('/auth/plex/callback');

    $response->assertRedirect(route('register'));
    expect(session('plex_registration'))->not->toBeNull();
});
