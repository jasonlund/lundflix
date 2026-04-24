<?php

use App\Models\Episode;
use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

it('fetches from API when no episodes passed and persists to database', function () {
    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60, 'type' => 'regular'],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSee('Pilot')
        ->assertSee('S01');

    expect(Episode::where('tvmaze_id', 1)->exists())->toBeTrue();
});

it('displays episodes grouped by season from API', function () {
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
    Http::fake([
        'api.tvmaze.com/shows/999/episodes?specials=1' => Http::response([], 404),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSet('error', null)
        ->assertSee(__('lundbergh.empty.episodes'));

    expect(Cache::has('tvmaze:episodes-failure:999'))->toBeFalse();
});

it('shows backoff error and caches failure when API request fails', function () {
    Http::fake([
        'api.tvmaze.com/shows/999/episodes?specials=1' => Http::response([], 500),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSet('error', __('lundbergh.error.episodes_backoff'))
        ->assertSee(__('lundbergh.error.episodes_backoff'));

    expect(Cache::has('tvmaze:episodes-failure:999'))->toBeTrue();
});

it('shows backoff error without hitting API when cache key exists', function () {
    Cache::put('tvmaze:episodes-failure:999', true, now()->addHour());

    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSet('error', __('lundbergh.error.episodes_backoff'))
        ->assertSee(__('lundbergh.error.episodes_backoff'));

    Http::assertNothingSent();
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
        ->assertSee(__('lundbergh.empty.episodes'));
});

it('does not hit API when episodes are passed directly', function () {
    $show = Show::factory()->create(['tvmaze_id' => 999]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'season' => 1,
        'number' => 1,
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => $show->episodes]);

    Http::assertNothingSent();
});

it('renders stable season and episode fragment anchors for deep links', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'Pilot',
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek(),
    ]);

    $html = Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])->html();

    expect($html)->toContain('id="season-s01"');
    expect($html)->toContain('data-season-anchor');
    expect($html)->toContain('id="episode-s01e01"');
});

it('renders hash navigation hooks for deep-linked season and episode anchors', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'Pilot',
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek(),
    ]);

    $html = Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])->html();

    expect($html)->toContain('window.addEventListener(\'hashchange\', handleHashChange)');
    expect($html)->toContain('handleHashNavigation()');
    expect($html)->toContain('accordionItem.hasAttribute(\'data-open\')');
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

it('renders checkbox for aired episodes but not for future episodes', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $pastEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'Aired Episode',
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek(),
    ]);

    $futureEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 101,
        'name' => 'Future Episode',
        'season' => 1,
        'number' => 2,
        'airdate' => now()->addWeek(),
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$pastEpisode, $futureEpisode])])
        ->assertSee('Aired Episode')
        ->assertSee('Future Episode')
        ->assertSeeHtml('value="S01E01"')
        ->assertDontSeeHtml('value="S01E02"');
});

it('treats empty string airdate from API as not aired', function () {
    Queue::fake();

    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'TBA', 'season' => 1, 'number' => 1, 'airdate' => '', 'runtime' => 60, 'type' => 'regular'],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSee('TBA')
        ->assertDontSeeHtml('value="S01E01"');
});

it('does not render checkbox for episodes with null airdate', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'TBA Episode',
        'season' => 1,
        'number' => 1,
        'airdate' => null,
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$episode])])
        ->assertSee('TBA Episode')
        ->assertDontSeeHtml('value="S01E01"');
});

it('does not render checkbox for episode airing later today based on per-episode airtime', function () {
    $this->travelTo(Carbon::parse('2026-04-19 12:00', 'America/New_York'));

    $show = Show::factory()->create([
        'tvmaze_id' => 1,
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
        'web_channel' => null,
    ]);

    $airedEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 100,
        'name' => 'Morning Episode',
        'season' => 1,
        'number' => 1,
        'airdate' => '2026-04-19',
        'airtime' => '08:00',
    ]);

    $laterEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 101,
        'name' => 'Evening Episode',
        'season' => 1,
        'number' => 2,
        'airdate' => '2026-04-19',
        'airtime' => '20:00',
    ]);

    Livewire::test('shows.episodes', ['show' => $show, 'episodes' => collect([$airedEpisode, $laterEpisode])])
        ->assertSee('Morning Episode')
        ->assertSee('Evening Episode')
        ->assertSeeHtml('value="S01E01"')
        ->assertDontSeeHtml('value="S01E02"');
});

it('filters insignificant specials from API response', function () {
    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2020-01-01', 'runtime' => 60, 'type' => 'regular'],
            ['id' => 2, 'name' => 'Behind the Scenes', 'season' => 1, 'number' => null, 'airdate' => '2020-01-08', 'runtime' => 30, 'type' => 'insignificant_special'],
            ['id' => 3, 'name' => 'Episode 2', 'season' => 1, 'number' => 2, 'airdate' => '2020-01-15', 'runtime' => 60, 'type' => 'regular'],
        ]),
    ]);

    $show = Show::factory()->create(['tvmaze_id' => 1]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->assertSee('Pilot')
        ->assertSee('Episode 2')
        ->assertDontSee('Behind the Scenes');
});
