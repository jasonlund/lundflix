<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

it('displays the registration form with plex data', function () {
    $this->withSession([
        'plex_registration' => [
            'plex_id' => 999,
            'plex_token' => 'test-token',
            'plex_username' => 'plexuser',
            'plex_email' => 'plexuser@example.com',
            'plex_thumb' => 'https://plex.tv/avatar.jpg',
        ],
    ]);

    Livewire::test(Register::class)
        ->assertSet('plexUsername', 'plexuser')
        ->assertSet('plexEmail', 'plexuser@example.com')
        ->assertSet('name', 'plexuser')
        ->assertSee('plexuser')
        ->assertSee('plexuser@example.com');
});

it('redirects to plex auth without session data', function () {
    Livewire::test(Register::class)
        ->assertRedirect(route('auth.plex'));
});

it('creates a user on successful registration', function () {
    $this->withSession([
        'plex_registration' => [
            'plex_id' => 999,
            'plex_token' => 'test-token',
            'plex_username' => 'plexuser',
            'plex_email' => 'plexuser@example.com',
            'plex_thumb' => 'https://plex.tv/avatar.jpg',
        ],
    ]);

    Livewire::test(Register::class)
        ->set('name', 'My Display Name')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect('/');

    $user = User::where('plex_id', '999')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('My Display Name')
        ->and($user->email)->toBe('plexuser@example.com')
        ->and($user->plex_username)->toBe('plexuser')
        ->and($user->plex_token)->toBe('test-token')
        ->and($user->plex_thumb)->toBe('https://plex.tv/avatar.jpg');

    $this->assertAuthenticatedAs($user);
    expect(session('plex_registration'))->toBeNull();
});

it('validates name is required', function () {
    $this->withSession([
        'plex_registration' => [
            'plex_id' => 999,
            'plex_token' => 'test-token',
            'plex_username' => 'plexuser',
            'plex_email' => 'plexuser@example.com',
            'plex_thumb' => 'https://plex.tv/avatar.jpg',
        ],
    ]);

    Livewire::test(Register::class)
        ->set('name', '')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['name' => 'required']);
});

it('validates password confirmation', function () {
    $this->withSession([
        'plex_registration' => [
            'plex_id' => 999,
            'plex_token' => 'test-token',
            'plex_username' => 'plexuser',
            'plex_email' => 'plexuser@example.com',
            'plex_thumb' => 'https://plex.tv/avatar.jpg',
        ],
    ]);

    Livewire::test(Register::class)
        ->set('name', 'My Name')
        ->set('password', 'password123')
        ->set('password_confirmation', 'different')
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('redirects to plex auth if session expires during registration', function () {
    $this->withSession([
        'plex_registration' => [
            'plex_id' => 999,
            'plex_token' => 'test-token',
            'plex_username' => 'plexuser',
            'plex_email' => 'plexuser@example.com',
            'plex_thumb' => 'https://plex.tv/avatar.jpg',
        ],
    ]);

    $component = Livewire::test(Register::class)
        ->set('name', 'My Name')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123');

    // Clear the session to simulate expiration
    session()->forget('plex_registration');

    $component->call('register')
        ->assertRedirect(route('auth.plex'));

    expect(User::count())->toBe(0);
});
