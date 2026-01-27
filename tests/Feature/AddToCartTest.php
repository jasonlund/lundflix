<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

beforeEach(function () {
    session()->flush();
});

it('shows add to cart button on movie page', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertSeeLivewire('cart.add-movie-button');
});

it('can add movie to cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('inCart', false)
        ->call('toggle')
        ->assertSet('inCart', true)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($movie->id))->toBeTrue();
});

it('can remove movie from cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('inCart', true)
        ->call('toggle')
        ->assertSet('inCart', false)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($movie->id))->toBeFalse();
});

it('shows correct button text when not in cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSee('Add to Cart');
});

it('shows correct button text when in cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSee('Remove');
});
