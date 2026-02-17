<?php

use App\Enums\Language;
use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakeTmdbFind(string $imdbId, ?int $tmdbId): array
{
    $key = "api.themoviedb.org/3/find/{$imdbId}*";

    if ($tmdbId === null) {
        return [$key => Http::response(['movie_results' => []])];
    }

    return [$key => Http::response([
        'movie_results' => [['id' => $tmdbId, 'title' => 'Test Movie']],
    ])];
}

function fakeTmdbDetails(int $tmdbId, array $overrides = []): array
{
    $defaults = [
        'id' => $tmdbId,
        'release_date' => '1994-09-23',
        'production_companies' => [
            ['id' => 97, 'name' => 'Castle Rock Entertainment', 'logo_path' => '/logo.png', 'origin_country' => 'US'],
        ],
        'spoken_languages' => [
            ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ],
        'original_language' => 'en',
        'original_title' => 'The Shawshank Redemption',
        'tagline' => 'Fear can hold you prisoner. Hope can set you free.',
        'status' => 'Released',
        'budget' => 25000000,
        'revenue' => 58300000,
        'origin_country' => ['US'],
        'release_dates' => ['results' => []],
        'alternative_titles' => ['titles' => []],
    ];

    return ["api.themoviedb.org/3/movie/{$tmdbId}*" => Http::response(array_merge($defaults, $overrides))];
}

function fakeTmdbChanges(array $tmdbIds = []): array
{
    return ['api.themoviedb.org/3/movie/changes*' => Http::response([
        'results' => array_map(fn (int $id) => ['id' => $id, 'adult' => false], $tmdbIds),
        'page' => 1,
        'total_pages' => 1,
        'total_results' => count($tmdbIds),
    ])];
}

it('syncs tmdb data for unsynced movies', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, [
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'US', 'release_dates' => [
                        ['type' => 3, 'release_date' => '1994-10-14T00:00:00.000Z'],
                        ['type' => 4, 'release_date' => '1999-09-21T00:00:00.000Z'],
                    ]],
                ],
            ],
            'alternative_titles' => [
                'titles' => [
                    ['iso_3166_1' => 'FR', 'title' => 'Les Évadés', 'type' => ''],
                    ['iso_3166_1' => 'BR', 'title' => 'Um Sonho de Liberdade', 'type' => ''],
                ],
            ],
        ]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->tmdb_id)->toBe(278)
        ->and($movie->release_date->format('Y-m-d'))->toBe('1994-09-23')
        ->and($movie->digital_release_date->format('Y-m-d'))->toBe('1999-09-21')
        ->and($movie->production_companies)->toHaveCount(1)
        ->and($movie->production_companies[0]['name'])->toBe('Castle Rock Entertainment')
        ->and($movie->spoken_languages)->toBe([Language::English])
        ->and($movie->alternative_titles)->toHaveCount(2)
        ->and($movie->original_language)->toBe(Language::English)
        ->and($movie->original_title)->toBe('The Shawshank Redemption')
        ->and($movie->tagline)->toBe('Fear can hold you prisoner. Hope can set you free.')
        ->and($movie->status)->toBe(MovieStatus::Released)
        ->and($movie->budget)->toBe(25000000)
        ->and($movie->revenue)->toBe(58300000)
        ->and($movie->origin_country)->toBe(['US'])
        ->and($movie->release_dates)->toBeArray()
        ->and($movie->tmdb_synced_at)->not->toBeNull();

    $this->assertDatabaseHas('movies', ['imdb_id' => 'tt0111161', 'status' => 'Released']);
});

it('updates recently changed movies via changes endpoint', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'imdb_id' => 'tt0111161',
        'tmdb_id' => 278,
        'digital_release_date' => null,
    ]);

    Http::fake([
        ...fakeTmdbChanges([278]),
        ...fakeTmdbDetails(278, [
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'US', 'release_dates' => [
                        ['type' => 4, 'release_date' => '2024-01-15T00:00:00.000Z'],
                    ]],
                ],
            ],
        ]),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->digital_release_date->format('Y-m-d'))->toBe('2024-01-15');
});

it('skips already-synced movies not in changes list', function () {
    Movie::factory()->withTmdbData()->create([
        'imdb_id' => 'tt0111161',
        'tmdb_id' => 278,
    ]);

    Http::fake([
        ...fakeTmdbChanges([999]),
    ]);

    $this->artisan('tmdb:sync-movies')
        ->expectsOutputToContain('No recently changed movies to update.')
        ->assertSuccessful();
});

it('reports when all movies are up to date', function () {
    Movie::factory()->withTmdbData()->create();

    Http::fake([
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')
        ->expectsOutputToContain('All movies are up to date with TMDB.')
        ->assertSuccessful();
});

it('processes all movies with --fresh flag', function () {
    $movie = Movie::factory()->withTmdbData()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278),
    ]);

    $this->artisan('tmdb:sync-movies', ['--fresh' => true])->assertSuccessful();

    $movie->refresh();

    expect($movie->tmdb_synced_at)->not->toBeNull();
    // find + details, no changes endpoint call
    Http::assertSentCount(2);
});

it('respects --limit option', function () {
    Movie::factory()->count(5)->sequence(
        ['imdb_id' => 'tt0000001'],
        ['imdb_id' => 'tt0000002'],
        ['imdb_id' => 'tt0000003'],
        ['imdb_id' => 'tt0000004'],
        ['imdb_id' => 'tt0000005'],
    )->create();

    Http::fake([
        'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => []]),
    ]);

    $this->artisan('tmdb:sync-movies', ['--limit' => 2])->assertSuccessful();

    expect(Movie::whereNotNull('tmdb_synced_at')->count())->toBe(2);
});

it('marks movie as synced when not found on tmdb', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        ...fakeTmdbFind('tt9999999', null),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->tmdb_synced_at)->not->toBeNull()
        ->and($movie->tmdb_id)->toBeNull()
        ->and($movie->release_date)->toBeNull();
});

it('stores tmdb id even when details return 404', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        'api.themoviedb.org/3/movie/278*' => Http::response([], 404),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->tmdb_id)->toBe(278)
        ->and($movie->tmdb_synced_at)->not->toBeNull()
        ->and($movie->release_date)->toBeNull();
});

it('handles empty release date gracefully', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, ['release_date' => '']),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->release_date)->toBeNull()
        ->and($movie->digital_release_date)->toBeNull();
});

it('stores null digital release date when no us digital release exists', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, [
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'US', 'release_dates' => [
                        ['type' => 3, 'release_date' => '1994-10-14T00:00:00.000Z'],
                    ]],
                ],
            ],
        ]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->digital_release_date)->toBeNull();
});

it('stores null digital release date when no us releases exist', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, [
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'FR', 'release_dates' => [
                        ['type' => 4, 'release_date' => '1995-03-15T00:00:00.000Z'],
                    ]],
                ],
            ],
        ]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->digital_release_date)->toBeNull();
});

it('syncs multiple movies concurrently', function () {
    $movie1 = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $movie2 = Movie::factory()->create(['imdb_id' => 'tt0068646']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbFind('tt0068646', 238),
        ...fakeTmdbDetails(278),
        ...fakeTmdbDetails(238, ['release_date' => '1972-03-14']),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    expect($movie1->refresh()->tmdb_id)->toBe(278)
        ->and($movie1->tmdb_synced_at)->not->toBeNull()
        ->and($movie2->refresh()->tmdb_id)->toBe(238)
        ->and($movie2->tmdb_synced_at)->not->toBeNull();
});

it('handles mixed found and not-found movies in same batch', function () {
    $found = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $notFound = Movie::factory()->create(['imdb_id' => 'tt9999999']);
    $details404 = Movie::factory()->create(['imdb_id' => 'tt0068646']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbFind('tt9999999', null),
        ...fakeTmdbFind('tt0068646', 238),
        ...fakeTmdbDetails(278),
        'api.themoviedb.org/3/movie/238*' => Http::response([], 404),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    expect($found->refresh()->tmdb_id)->toBe(278)
        ->and($found->release_date)->not->toBeNull()
        ->and($notFound->refresh()->tmdb_id)->toBeNull()
        ->and($notFound->tmdb_synced_at)->not->toBeNull()
        ->and($details404->refresh()->tmdb_id)->toBe(238)
        ->and($details404->release_date)->toBeNull()
        ->and($details404->tmdb_synced_at)->not->toBeNull();
});

it('stores null for zero budget and revenue', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, [
            'budget' => 0,
            'revenue' => 0,
        ]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->budget)->toBeNull()
        ->and($movie->revenue)->toBeNull();
});

it('syncs non-released movie status', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, [
            'status' => 'In Production',
        ]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->status)->toBe(MovieStatus::InProduction);
});

it('stores full release dates from all countries', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    $releaseDates = [
        'results' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => '1994-10-14T00:00:00.000Z'],
            ]],
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 1, 'release_date' => '1994-09-10T00:00:00.000Z', 'note' => 'Cannes Film Festival'],
            ]],
        ],
    ];

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, ['release_dates' => $releaseDates]),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->release_dates)->toHaveCount(2)
        ->and($movie->release_dates[0]['iso_3166_1'])->toBe('US')
        ->and($movie->release_dates[1]['iso_3166_1'])->toBe('FR');
});

it('stores null tagline for empty string', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        ...fakeTmdbFind('tt0111161', 278),
        ...fakeTmdbDetails(278, ['tagline' => '']),
        ...fakeTmdbChanges(),
    ]);

    $this->artisan('tmdb:sync-movies')->assertSuccessful();

    $movie->refresh();

    expect($movie->tagline)->toBeNull();
});
