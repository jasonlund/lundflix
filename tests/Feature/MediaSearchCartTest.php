<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Services\CartService;
use App\Support\Formatters;
use Livewire\Livewire;

it('does not display add to cart button in search results', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt1111111']);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt1111111')
        ->assertDontSeeLivewire('cart.add-movie-button');
});

it('shows movie metadata in search results', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'Test Movie Title',
        'year' => 2024,
        'runtime' => 125,
        'genres' => ['Action'],
        'release_date' => '2024-06-15',
        'production_companies' => [
            ['id' => 1, 'name' => 'Test Studios', 'logo_path' => null, 'origin_country' => 'US'],
        ],
    ]);
    $runtime = Formatters::runtime($movie->runtime);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt3333333')
        ->assertSee('06/15/24')
        ->assertSee('Test Studios')
        ->assertSee($runtime);
});

it('shows show metadata in search results', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create([
        'imdb_id' => 'tt4444444',
        'name' => 'Test Show Name',
        'premiered' => '2018-01-01',
        'ended' => '2020-01-01',
        'status' => 'Ended',
        'runtime' => 42,
        'genres' => ['Drama'],
        'network' => ['name' => 'HBO'],
    ]);

    Livewire::actingAs($user)
        ->test('media-search')
        ->set('query', 'tt4444444')
        ->assertSee("'18-'20")
        ->assertSee('42m')
        ->assertSee('HBO');
});

it('can add movie to cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt5555555']);

    Livewire::actingAs($user)
        ->test('cart.add-movie-button', ['movie' => $movie])
        ->call('toggle');

    expect(app(CartService::class)->has($movie->id))->toBeTrue();
});
