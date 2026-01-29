<?php

use App\Models\Show;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('extracts imdb_id from externals when syncing shows', function () {
    Http::fake([
        'api.tvmaze.com/shows?page=0' => Http::response([
            [
                'id' => 112,
                'name' => 'South Park',
                'type' => 'Scripted',
                'language' => 'English',
                'genres' => ['Comedy', 'Animation'],
                'status' => 'Running',
                'runtime' => 30,
                'premiered' => '1997-08-13',
                'ended' => null,
                'officialSite' => 'https://www.southparkstudios.com/',
                'schedule' => ['time' => '22:00', 'days' => ['Wednesday']],
                'rating' => ['average' => 8.6],
                'weight' => 98,
                'network' => ['id' => 23, 'name' => 'Comedy Central'],
                'webChannel' => null,
                'externals' => [
                    'tvrage' => 5266,
                    'thetvdb' => 75897,
                    'imdb' => 'tt0121955',
                ],
                'image' => ['medium' => 'https://example.com/medium.jpg', 'original' => 'https://example.com/original.jpg'],
                'summary' => '<p>South Park is an animated comedy.</p>',
                'updated' => 1704067200,
            ],
        ]),
        'api.tvmaze.com/shows?page=1' => Http::response([], 404),
    ]);

    $this->artisan('tvmaze:sync-shows', ['--fresh' => true])
        ->assertSuccessful();

    $show = Show::where('tvmaze_id', 112)->first();

    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('South Park')
        ->and($show->imdb_id)->toBe('tt0121955')
        ->and($show->thetvdb_id)->toBe(75897);
});

it('handles shows without imdb_id in externals', function () {
    Http::fake([
        'api.tvmaze.com/shows?page=0' => Http::response([
            [
                'id' => 999,
                'name' => 'Mystery Show',
                'type' => 'Scripted',
                'language' => 'English',
                'genres' => ['Drama'],
                'status' => 'Running',
                'runtime' => 60,
                'premiered' => '2020-01-01',
                'ended' => null,
                'officialSite' => null,
                'schedule' => ['time' => '21:00', 'days' => ['Monday']],
                'rating' => ['average' => 7.5],
                'weight' => 50,
                'network' => ['id' => 1, 'name' => 'NBC'],
                'webChannel' => null,
                'externals' => [
                    'tvrage' => null,
                    'thetvdb' => 12345,
                    'imdb' => null,
                ],
                'image' => null,
                'summary' => null,
                'updated' => 1704067200,
            ],
        ]),
        'api.tvmaze.com/shows?page=1' => Http::response([], 404),
    ]);

    $this->artisan('tvmaze:sync-shows', ['--fresh' => true])
        ->assertSuccessful();

    $show = Show::where('tvmaze_id', 999)->first();

    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('Mystery Show')
        ->and($show->imdb_id)->toBeNull()
        ->and($show->thetvdb_id)->toBe(12345);
});
