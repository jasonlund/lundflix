<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

beforeEach(function () {
    session()->flush();
});

it('shows empty cart message when no items', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 0)
        ->assertSee('Your cart is empty');
});

it('shows cart count when items present', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 1);
});

it('updates count when cart-updated event received', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 0);

    app(CartService::class)->toggleMovie($movie->id);

    $component->dispatch('cart-updated')
        ->assertSet('itemCount', 1);
});

it('displays movie title in cart dropdown', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie Title']);
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee('Test Movie Title');
});

it('shows checkout button when items in cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee('Checkout');
});
