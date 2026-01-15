<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Episode;
use App\Models\Show;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('displays episodes from database when available', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'DB Episode',
        'season' => 1,
        'number' => 1,
    ]);

    // No HTTP fake needed - should not call API
    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show])
        ->assertSee('DB Episode')
        ->assertSee('Season 1');

    Http::assertNothingSent();
});

it('fetches from API when database is empty and dispatches job', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/1/episodes' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show])
        ->assertSee('Pilot')
        ->assertSee('Season 1');

    Queue::assertPushed(StoreShowEpisodes::class, function ($job) use ($show) {
        return $job->show->id === $show->id;
    });
});

it('displays episodes grouped by season from API', function () {
    Queue::fake();

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
    Queue::fake();

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

it('handles show with no episodes from API', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/999/episodes' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show])
        ->assertSee('No episodes available.');

    Queue::assertNothingPushed();
});

it('does not dispatch job when no episodes returned from API', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/999/episodes' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::withoutLazyLoading()
        ->test('shows.episodes', ['show' => $show]);

    Queue::assertNothingPushed();
});
