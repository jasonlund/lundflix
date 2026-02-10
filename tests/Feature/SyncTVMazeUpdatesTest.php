<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('updates existing shows that have changed in TVMaze', function () {
    // Create an existing show that will be updated
    Show::factory()->create([
        'tvmaze_id' => 112,
        'name' => 'South Park (Old Name)',
        'status' => 'Running',
        'ended' => null,
    ]);

    Http::fake([
        'api.tvmaze.com/updates/shows?since=day' => Http::response([
            '112' => 1704067200, // This show was updated
            '999' => 1704067200, // This show we don't have
        ]),
        'api.tvmaze.com/shows/112' => Http::response([
            'id' => 112,
            'name' => 'South Park',
            'type' => 'Scripted',
            'language' => 'English',
            'genres' => ['Comedy', 'Animation'],
            'status' => 'Ended',
            'runtime' => 30,
            'averageRuntime' => 30,
            'premiered' => '1997-08-13',
            'ended' => '2024-12-15',
            'schedule' => ['time' => '22:00', 'days' => ['Wednesday']],
            'network' => ['id' => 23, 'name' => 'Comedy Central'],
            'webChannel' => null,
            'externals' => [
                'tvrage' => 5266,
                'thetvdb' => 75897,
                'imdb' => 'tt0121955',
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-updates')
        ->assertSuccessful();

    $show = Show::where('tvmaze_id', 112)->first();

    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('South Park')
        ->and($show->status)->toBe(ShowStatus::Ended)
        ->and($show->ended->toDateString())->toBe('2024-12-15');
});

it('only fetches shows that exist in the database', function () {
    // Create one show we have
    Show::factory()->create(['tvmaze_id' => 100]);

    Http::fake([
        'api.tvmaze.com/updates/shows?since=day' => Http::response([
            '100' => 1704067200,
            '200' => 1704067200, // We don't have this one
            '300' => 1704067200, // Or this one
        ]),
        'api.tvmaze.com/shows/100' => Http::response([
            'id' => 100,
            'name' => 'Test Show',
            'type' => 'Scripted',
            'language' => 'English',
            'genres' => ['Drama'],
            'status' => 'Running',
            'runtime' => 60,
            'averageRuntime' => 60,
            'premiered' => '2020-01-01',
            'ended' => null,
            'schedule' => ['time' => '21:00', 'days' => ['Monday']],
            'network' => ['id' => 1, 'name' => 'NBC'],
            'webChannel' => null,
            'externals' => ['tvrage' => null, 'thetvdb' => null, 'imdb' => null],
        ]),
    ]);

    $this->artisan('tvmaze:sync-updates')
        ->assertSuccessful();

    // Should NOT have made requests for shows 200 or 300
    Http::assertSent(fn ($request) => str_contains($request->url(), '/shows/100'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/shows/200'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/shows/300'));
});

it('handles no tracked shows needing updates', function () {
    // Create a show, but it won't be in the updates list
    Show::factory()->create(['tvmaze_id' => 999]);

    Http::fake([
        'api.tvmaze.com/updates/shows?since=day' => Http::response([
            '100' => 1704067200, // Different show than what we have
        ]),
    ]);

    $this->artisan('tvmaze:sync-updates')
        ->assertSuccessful()
        ->expectsOutput('No tracked shows need updating.');
});

it('throws exception when updates endpoint fails', function () {
    Http::fake([
        'api.tvmaze.com/updates/shows?since=day' => Http::response(null, 500),
    ]);

    $this->artisan('tvmaze:sync-updates');
})->throws(\Illuminate\Http\Client\RequestException::class);

it('accepts since option for time period', function () {
    Show::factory()->create(['tvmaze_id' => 100]);

    Http::fake([
        'api.tvmaze.com/updates/shows?since=week' => Http::response([
            '100' => 1704067200,
        ]),
        'api.tvmaze.com/shows/100' => Http::response([
            'id' => 100,
            'name' => 'Test Show',
            'type' => 'Scripted',
            'language' => 'English',
            'genres' => [],
            'status' => 'Running',
            'runtime' => 60,
            'averageRuntime' => 60,
            'premiered' => '2020-01-01',
            'ended' => null,
            'schedule' => ['time' => '21:00', 'days' => []],
            'network' => null,
            'webChannel' => null,
            'externals' => ['tvrage' => null, 'thetvdb' => null, 'imdb' => null],
        ]),
    ]);

    $this->artisan('tvmaze:sync-updates', ['--since' => 'week'])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'since=week'));
});

it('handles shows that no longer exist in TVMaze', function () {
    Show::factory()->create(['tvmaze_id' => 100]);
    Show::factory()->create(['tvmaze_id' => 200]);

    Http::fake([
        'api.tvmaze.com/updates/shows?since=day' => Http::response([
            '100' => 1704067200,
            '200' => 1704067200,
        ]),
        'api.tvmaze.com/shows/100' => Http::response([
            'id' => 100,
            'name' => 'Existing Show',
            'type' => 'Scripted',
            'language' => 'English',
            'genres' => [],
            'status' => 'Running',
            'runtime' => 60,
            'averageRuntime' => 60,
            'premiered' => '2020-01-01',
            'ended' => null,
            'schedule' => ['time' => '21:00', 'days' => []],
            'network' => null,
            'webChannel' => null,
            'externals' => ['tvrage' => null, 'thetvdb' => null, 'imdb' => null],
        ]),
        'api.tvmaze.com/shows/200' => Http::response(null, 404),
    ]);

    $this->artisan('tvmaze:sync-updates')
        ->assertSuccessful();

    // Show 100 should be updated
    expect(Show::where('tvmaze_id', 100)->first()->name)->toBe('Existing Show');
});
