<?php

use App\Filament\Resources\Movies\Pages\ListMovies;
use App\Filament\Resources\Movies\Pages\ViewMovie;
use App\Models\Movie;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListMovies::class)
        ->assertSuccessful();
});

it('can render the view page', function () {
    $movie = Movie::factory()->create();

    Livewire::test(ViewMovie::class, ['record' => $movie->getRouteKey()])
        ->assertSuccessful();
});

it('displays movies in the list', function () {
    $movie = Movie::factory()->create([
        'title' => 'Test Movie Title',
        'imdb_id' => 'tt1234567',
    ]);

    Livewire::test(ListMovies::class)
        ->assertSee('Test Movie Title')
        ->assertSee('tt1234567');
});

it('displays movie details on view page', function () {
    $movie = Movie::factory()->create([
        'title' => 'Detailed Movie',
        'imdb_id' => 'tt9999999',
        'year' => 2024,
        'runtime' => 120,
    ]);

    Livewire::test(ViewMovie::class, ['record' => $movie->getRouteKey()])
        ->assertSee('Detailed Movie')
        ->assertSee('tt9999999')
        ->assertSee('2024');
});

it('does not show create button due to policy', function () {
    Livewire::test(ListMovies::class)
        ->assertDontSee('New movie');
});

it('does not show edit action due to policy', function () {
    $movie = Movie::factory()->create();

    Livewire::test(ViewMovie::class, ['record' => $movie->getRouteKey()])
        ->assertDontSee('Edit');
});
