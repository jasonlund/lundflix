<?php

use App\Models\User;
use Livewire\Livewire;

it('loads the authenticated user data on mount', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'timezone' => 'America/Chicago',
    ]);

    Livewire::actingAs($user)
        ->test('profile-form')
        ->assertSet('name', 'Test User')
        ->assertSet('email', 'test@example.com')
        ->assertSet('timezone', 'America/Chicago');
});

it('updates user name and timezone', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'timezone' => 'America/New_York',
    ]);

    Livewire::actingAs($user)
        ->test('profile-form')
        ->set('name', 'New Name')
        ->set('timezone', 'Europe/London')
        ->call('save');

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->timezone)->toBe('Europe/London');
});

it('validates name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('profile-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validates name max length', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('profile-form')
        ->set('name', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validates timezone is valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('profile-form')
        ->set('timezone', 'Invalid/Timezone')
        ->call('save')
        ->assertHasErrors(['timezone']);
});

it('does not update email', function () {
    $user = User::factory()->create(['email' => 'original@example.com']);

    Livewire::actingAs($user)
        ->test('profile-form')
        ->set('email', 'changed@example.com')
        ->call('save');

    $user->refresh();

    expect($user->email)->toBe('original@example.com');
});

it('is present on authenticated pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSeeLivewire('profile-form');
});
