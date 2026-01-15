<?php

use App\Models\Show;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('fetches and displays episodes grouped by season', function () {
    Http::fake([
        'api.tvmaze.com/shows/1/episodes' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60],
            ['id' => 2, 'name' => 'The Fire', 'season' => 1, 'number' => 2, 'airdate' => '2013-07-01', 'runtime' => 60],
            ['id' => 10, 'name' => 'Heads Will Roll', 'season' => 2, 'number' => 1, 'airdate' => '2014-06-30', 'runtime' => 60],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show])
        ->assertSee('Season 1')
        ->assertSee('Season 2')
        ->assertSee('Pilot')
        ->assertSee('The Fire')
        ->assertSee('Heads Will Roll');
});

it('displays episodes in order by episode number', function () {
    Http::fake([
        'api.tvmaze.com/shows/1/episodes' => Http::response([
            ['id' => 3, 'name' => 'Third', 'season' => 1, 'number' => 3, 'airdate' => null, 'runtime' => null],
            ['id' => 1, 'name' => 'First', 'season' => 1, 'number' => 1, 'airdate' => null, 'runtime' => null],
            ['id' => 2, 'name' => 'Second', 'season' => 1, 'number' => 2, 'airdate' => null, 'runtime' => null],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $component = Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show]);

    $episodes = $component->get('episodesBySeason')[1];

    expect($episodes[0]['name'])->toBe('First')
        ->and($episodes[1]['name'])->toBe('Second')
        ->and($episodes[2]['name'])->toBe('Third');
});

it('handles show with no episodes', function () {
    Http::fake([
        'api.tvmaze.com/shows/999/episodes' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show])
        ->assertSee('No episodes available.');
});
