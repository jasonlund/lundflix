<?php

use App\Jobs\StoreTMDBData;
use App\Models\Movie;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('stores tmdb data for a movie', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'The Shawshank Redemption'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => [
                ['id' => 97, 'name' => 'Castle Rock Entertainment', 'logo_path' => '/logo.png', 'origin_country' => 'US'],
            ],
            'spoken_languages' => [
                ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
            ],
            'original_language' => 'en',
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
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->tmdb_id)->toBe(278)
        ->and($movie->release_date->format('Y-m-d'))->toBe('1994-09-23')
        ->and($movie->digital_release_date->format('Y-m-d'))->toBe('1999-09-21')
        ->and($movie->production_companies)->toHaveCount(1)
        ->and($movie->production_companies[0]['name'])->toBe('Castle Rock Entertainment')
        ->and($movie->spoken_languages)->toHaveCount(1)
        ->and($movie->spoken_languages[0]['english_name'])->toBe('English')
        ->and($movie->alternative_titles)->toHaveCount(2)
        ->and($movie->alternative_titles[0]['title'])->toBe('Les Évadés')
        ->and($movie->original_language)->toBe('en')
        ->and($movie->tmdb_synced_at)->not->toBeNull();
});

it('marks movie as synced when not found on tmdb', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        'api.themoviedb.org/3/find/tt9999999*' => Http::response([
            'movie_results' => [],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->tmdb_synced_at)->not->toBeNull()
        ->and($movie->tmdb_id)->toBeNull()
        ->and($movie->release_date)->toBeNull()
        ->and($movie->production_companies)->toBeNull();
});

it('stores tmdb id even when details return 404', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'The Shawshank Redemption'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([], 404),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->tmdb_id)->toBe(278)
        ->and($movie->tmdb_synced_at)->not->toBeNull()
        ->and($movie->release_date)->toBeNull();
});

it('handles empty release date gracefully', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'Test Movie'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '',
            'production_companies' => [],
            'spoken_languages' => [],
            'original_language' => 'en',
            'release_dates' => ['results' => []],
            'alternative_titles' => ['titles' => []],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->release_date)->toBeNull()
        ->and($movie->digital_release_date)->toBeNull()
        ->and($movie->original_language)->toBe('en');
});

it('stores multiple production companies as json', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    $companies = [
        ['id' => 97, 'name' => 'Castle Rock Entertainment', 'logo_path' => '/logo1.png', 'origin_country' => 'US'],
        ['id' => 174, 'name' => 'Warner Bros.', 'logo_path' => '/logo2.png', 'origin_country' => 'US'],
    ];

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'Test Movie'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => $companies,
            'spoken_languages' => [],
            'original_language' => 'en',
            'release_dates' => ['results' => []],
            'alternative_titles' => ['titles' => []],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->production_companies)->toHaveCount(2)
        ->and($movie->production_companies[0]['name'])->toBe('Castle Rock Entertainment')
        ->and($movie->production_companies[1]['name'])->toBe('Warner Bros.');
});

it('updates existing tmdb data on re-run', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'imdb_id' => 'tt0111161',
        'tmdb_id' => 278,
        'original_language' => 'fr',
    ]);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'The Shawshank Redemption'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => [
                ['id' => 97, 'name' => 'Castle Rock Entertainment', 'logo_path' => '/logo.png', 'origin_country' => 'US'],
            ],
            'spoken_languages' => [
                ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
            ],
            'original_language' => 'en',
            'release_dates' => ['results' => []],
            'alternative_titles' => ['titles' => []],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->original_language)->toBe('en');
});

it('stores null digital release date when no us digital release exists', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'Test Movie'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => [],
            'spoken_languages' => [],
            'original_language' => 'en',
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'US', 'release_dates' => [
                        ['type' => 3, 'release_date' => '1994-10-14T00:00:00.000Z'],
                    ]],
                ],
            ],
            'alternative_titles' => ['titles' => []],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->digital_release_date)->toBeNull();
});

it('stores null digital release date when no us releases exist', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'Test Movie'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => [],
            'spoken_languages' => [],
            'original_language' => 'en',
            'release_dates' => [
                'results' => [
                    ['iso_3166_1' => 'FR', 'release_dates' => [
                        ['type' => 4, 'release_date' => '1995-03-15T00:00:00.000Z'],
                    ]],
                ],
            ],
            'alternative_titles' => ['titles' => []],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->digital_release_date)->toBeNull();
});

it('stores alternative titles as json', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    $titles = [
        ['iso_3166_1' => 'FR', 'title' => 'Les Évadés', 'type' => ''],
        ['iso_3166_1' => 'BR', 'title' => 'Um Sonho de Liberdade', 'type' => ''],
        ['iso_3166_1' => 'DE', 'title' => 'Die Verurteilten', 'type' => ''],
    ];

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                ['id' => 278, 'title' => 'Test Movie'],
            ],
        ]),
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'release_date' => '1994-09-23',
            'production_companies' => [],
            'spoken_languages' => [],
            'original_language' => 'en',
            'release_dates' => ['results' => []],
            'alternative_titles' => ['titles' => $titles],
        ]),
    ]);

    StoreTMDBData::dispatchSync($movie);

    $movie->refresh();

    expect($movie->alternative_titles)->toHaveCount(3)
        ->and($movie->alternative_titles[0]['title'])->toBe('Les Évadés')
        ->and($movie->alternative_titles[2]['iso_3166_1'])->toBe('DE');
});
