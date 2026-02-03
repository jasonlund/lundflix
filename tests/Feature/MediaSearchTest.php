<?php

use App\Models\Movie;
use App\Models\Show;
use App\Support\Formatters;
use Livewire\Livewire;

it('displays show results in search', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'premiered' => '2008-01-20',
        'ended' => '2013-09-29',
        'status' => 'Ended',
        'runtime' => 47,
        'genres' => ['Drama', 'Crime'],
        'network' => ['name' => 'AMC', 'country' => ['name' => 'United States']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Breaking')
        ->assertSee('2008-2013')
        ->assertSee('Ended')
        ->assertSee('47 min')
        ->assertSee('Drama')
        ->assertSee('AMC (United States)')
        ->assertSee(route('shows.show', $show));
});

it('displays movie results in search', function () {
    $movie = Movie::factory()->create([
        'title' => 'The Matrix',
        'year' => 1999,
        'runtime' => 136,
        'genres' => ['Action', 'Sci-Fi'],
    ]);
    $runtime = Formatters::runtime($movie->runtime);

    Livewire::test('media-search')
        ->set('query', 'Matrix')
        ->assertSee('1999')
        ->assertSee($runtime)
        ->assertSee('Action')
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

it('uses black active styling in search results', function () {
    Show::factory()->create(['name' => 'The Wire']);

    Livewire::test('media-search')
        ->set('query', 'Wire')
        ->assertSee('data-active:!bg-black')
        ->assertSee('group-data-active/item:text-zinc-400');
});
