<?php

use App\Models\Movie;
use App\Models\Show;
use Livewire\Livewire;

it('displays show results in search', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    Livewire::test('media-search')
        ->set('query', 'Breaking')
        ->assertSee('Breaking Bad')
        ->assertSee(route('shows.show', $show));
});

it('displays movie results in search', function () {
    $movie = Movie::factory()->create(['title' => 'The Matrix']);

    Livewire::test('media-search')
        ->set('query', 'Matrix')
        ->assertSee('The Matrix')
        ->assertSee(route('movies.show', $movie));
});

it('clears search query when clearSearch is called', function () {
    Livewire::test('media-search')
        ->set('query', 'test query')
        ->assertSet('query', 'test query')
        ->call('clearSearch')
        ->assertSet('query', '');
});

it('renders the search modal component', function () {
    Livewire::test('media-search')
        ->assertStatus(200);
});
