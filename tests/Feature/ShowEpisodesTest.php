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

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'DB Episode',
        'season' => 1,
        'number' => 1,
    ]);

    // Pass episodes directly - no HTTP call needed
    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])
        ->assertSee('DB Episode')
        ->assertSee('S01');

    Http::assertNothingSent();
});

it('fetches from API when no episodes passed and dispatches job', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60, 'type' => 'regular'],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSee('Pilot')
        ->assertSee('S01');

    Queue::assertPushed(StoreShowEpisodes::class, function ($job) use ($show) {
        return $job->show->id === $show->id;
    });
});

it('displays episodes grouped by season from API', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60, 'type' => 'regular'],
            ['id' => 2, 'name' => 'The Fire', 'season' => 1, 'number' => 2, 'airdate' => '2013-07-01', 'runtime' => 60, 'type' => 'regular'],
            ['id' => 10, 'name' => 'Heads Will Roll', 'season' => 2, 'number' => 1, 'airdate' => '2014-06-30', 'runtime' => 60, 'type' => 'regular'],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSee('S01')
        ->assertSee('S02')
        ->assertSee('Pilot')
        ->assertSee('The Fire')
        ->assertSee('Heads Will Roll');
});

it('handles show with no episodes from API', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/999/episodes?specials=1' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSet('error', null)
        ->assertSee('No episodes available.');

    Queue::assertNothingPushed();
});

it('shows error when API request fails', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/999/episodes?specials=1' => Http::response([], 500),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSet('error', 'Failed to load episodes from TVMaze.')
        ->assertSee('Failed to load episodes from TVMaze.');

    Queue::assertNothingPushed();
});

it('handles null episodes state gracefully', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'season' => 1,
        'number' => 1,
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])
        ->set('episodes', null)
        ->assertSee('No episodes available.');
});

it('does not dispatch job when no episodes returned from API', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/999/episodes?specials=1' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show]);

    Queue::assertNothingPushed();
});

it('displays episode checkboxes', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'Test Episode',
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek(),
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])
        ->assertSee('Test Episode')
        ->assertSeeHtml('data-flux-checkbox');
});

it('syncs episodes to cart in display order', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    // Create episodes out of display order
    $episodes = collect([
        Episode::factory()->create(['show_id' => $show->id, 'tvmaze_id' => 1, 'season' => 2, 'number' => 1, 'airdate' => now()->subMonths(6)]),
        Episode::factory()->create(['show_id' => $show->id, 'tvmaze_id' => 2, 'season' => 1, 'number' => 2, 'airdate' => now()->subYear()]),
        Episode::factory()->create(['show_id' => $show->id, 'tvmaze_id' => 3, 'season' => 1, 'number' => 1, 'airdate' => now()->subYear()->subWeek()]),
    ]);

    // Call syncToCart with codes in random order (S02E01, S01E02, S01E01)
    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => $episodes])
        ->call('syncToCart', ['S02E01', 'S01E02', 'S01E01'])
        ->assertDispatched('cart-updated');

    // Verify cart has episodes in display order (S01E01, S01E02, S02E01)
    $cartEpisodes = app(\App\Services\CartService::class)->episodes();
    expect($cartEpisodes)->toHaveCount(3);
    expect($cartEpisodes[0]['code'])->toBe('s01e01');
    expect($cartEpisodes[1]['code'])->toBe('s01e02');
    expect($cartEpisodes[2]['code'])->toBe('s02e01');
});
