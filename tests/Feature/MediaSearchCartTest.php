<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

it('displays add to cart button for movies in search results', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt1111111']);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt1111111')
        ->assertSeeLivewire('cart.add-movie-button');
});

it('does not display add to cart button for shows in search results', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt2222222']);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt2222222')
        ->assertDontSeeLivewire('cart.add-movie-button');
});

it('shows movie title in search results', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'Test Movie Title',
    ]);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt3333333')
        ->assertSee('Test Movie Title');
});

it('shows show name in search results', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create([
        'imdb_id' => 'tt4444444',
        'name' => 'Test Show Name',
    ]);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt4444444')
        ->assertSee('Test Show Name');
});

it('can add movie to cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt5555555']);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->call('toggle');

    expect(app(CartService::class)->has($movie->id))->toBeTrue();
});
