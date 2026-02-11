<?php

use App\Services\TMDBService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('finds a movie by imdb id', function () {
    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [
                [
                    'id' => 278,
                    'title' => 'The Shawshank Redemption',
                    'release_date' => '1994-09-23',
                    'original_language' => 'en',
                ],
            ],
            'tv_results' => [],
        ]),
    ]);

    $service = new TMDBService;
    $result = $service->findByImdbId('tt0111161');

    expect($result)
        ->not->toBeNull()
        ->and($result['id'])->toBe(278)
        ->and($result['title'])->toBe('The Shawshank Redemption');
});

it('returns null when movie is not found on tmdb', function () {
    Http::fake([
        'api.themoviedb.org/3/find/tt9999999*' => Http::response([
            'movie_results' => [],
            'tv_results' => [],
        ]),
    ]);

    $service = new TMDBService;
    $result = $service->findByImdbId('tt9999999');

    expect($result)->toBeNull();
});

it('fetches movie details with appended release dates and alternative titles', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'title' => 'The Shawshank Redemption',
            'release_date' => '1994-09-23',
            'original_language' => 'en',
            'production_companies' => [
                ['id' => 97, 'name' => 'Castle Rock Entertainment', 'logo_path' => '/logo.png', 'origin_country' => 'US'],
            ],
            'spoken_languages' => [
                ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
            ],
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
                ],
            ],
        ]),
    ]);

    $service = new TMDBService;
    $details = $service->movieDetails(278);

    expect($details)
        ->not->toBeNull()
        ->and($details['id'])->toBe(278)
        ->and($details['production_companies'])->toHaveCount(1)
        ->and($details['release_dates']['results'])->toHaveCount(1)
        ->and($details['alternative_titles']['titles'])->toHaveCount(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'append_to_response=release_dates%2Calternative_titles')
        || str_contains($request->url(), 'append_to_response=release_dates,alternative_titles'));
});

it('returns null when movie details return 404', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/999999*' => Http::response([], 404),
    ]);

    $service = new TMDBService;
    $details = $service->movieDetails(999999);

    expect($details)->toBeNull();
});

it('throws on server errors for find endpoint', function () {
    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([], 500),
    ]);

    $service = new TMDBService;
    $service->findByImdbId('tt0111161');
})->throws(RequestException::class);

it('throws on non-404 errors for movie details', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/278*' => Http::response([], 500),
    ]);

    $service = new TMDBService;
    $service->movieDetails(278);
})->throws(RequestException::class);

it('sends bearer token with requests', function () {
    config(['services.tmdb.api_key' => 'test-tmdb-token']);

    Http::fake([
        'api.themoviedb.org/3/find/tt0111161*' => Http::response([
            'movie_results' => [],
            'tv_results' => [],
        ]),
    ]);

    $service = new TMDBService;
    $service->findByImdbId('tt0111161');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-tmdb-token'));
});
