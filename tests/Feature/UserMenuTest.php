<?php

use App\Models\User;
use Livewire\Livewire;

it('renders the user menu component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('user-menu')
        ->assertSuccessful()
        ->assertSee('Profile')
        ->assertSee('Logout');
});

it('re-renders avatar initials on profile-updated event', function () {
    $user = User::factory()->create(['name' => 'Alpha Bravo']);

    $component = Livewire::actingAs($user)
        ->test('user-menu')
        ->assertSeeHtml('>AB</');

    $user->update(['name' => 'Charlie Delta']);

    $component
        ->dispatch('profile-updated')
        ->assertSeeHtml('>CD</')
        ->assertDontSeeHtml('>AB</');
});

it('is present on authenticated pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSeeLivewire('user-menu');
});
