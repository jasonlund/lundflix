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

// --- Disabled state for unreleased movies ---

it('disables button for unreleased movie statuses', function (string $rawStatus) {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => $rawStatus,
        'release_date' => now()->addYear(),
        'digital_release_date' => null,
        'release_dates' => [],
    ]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('isDisabled', true)
        ->assertSee('Add to Cart')
        ->assertDontSee('Remove');
})->with([
    'Rumored',
    'Planned',
    'In Production',
    'Post Production',
]);

it('disables button for canceled movies', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Canceled',
        'release_date' => null,
        'digital_release_date' => null,
        'release_dates' => [],
    ]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('isDisabled', true);
});

it('enables button for released movies', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => '2020-01-01',
        'release_dates' => [],
    ]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('isDisabled', false);
});

it('enables button for movies with null status', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['status' => null]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->assertSet('isDisabled', false);
});

it('prevents toggling for unreleased movies', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Planned',
        'release_date' => now()->addYear(),
        'digital_release_date' => null,
        'release_dates' => [],
    ]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->call('toggle')
        ->assertSet('inCart', false)
        ->assertNotDispatched('cart-updated');

    expect(app(CartService::class)->has($movie->id))->toBeFalse();
});

it('prevents toggling for canceled movies', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Canceled',
        'release_date' => null,
        'digital_release_date' => null,
        'release_dates' => [],
    ]);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->call('toggle')
        ->assertSet('inCart', false)
        ->assertNotDispatched('cart-updated');

    expect(app(CartService::class)->has($movie->id))->toBeFalse();
});
