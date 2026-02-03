<?php

use App\Services\FanartTVService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('fetches movie artwork by imdb id', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'tmdb_id' => '278',
            'imdb_id' => 'tt0111161',
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/shawshank.png', 'lang' => 'en', 'likes' => '5'],
            ],
            'movieposter' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/fanart/movies/278/movieposter/shawshank.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    $service = new FanartTVService;
    $artwork = $service->movie('tt0111161');

    expect($artwork)
        ->not->toBeNull()
        ->and($artwork['name'])->toBe('The Shawshank Redemption')
        ->and($artwork['imdb_id'])->toBe('tt0111161')
        ->and($artwork['hdmovielogo'])->toHaveCount(1)
        ->and($artwork['movieposter'])->toHaveCount(1);
});

it('returns null when movie has no artwork', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    $service = new FanartTVService;
    $artwork = $service->movie('tt9999999');

    expect($artwork)->toBeNull();
});

it('fetches tv show artwork by tvdb id', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'name' => 'Under the Dome',
            'thetvdb_id' => '264492',
            'hdtvlogo' => [
                ['id' => '11111', 'url' => 'https://assets.fanart.tv/fanart/tv/264492/hdtvlogo/under-the-dome.png', 'lang' => 'en', 'likes' => '3'],
            ],
            'tvposter' => [
                ['id' => '22222', 'url' => 'https://assets.fanart.tv/fanart/tv/264492/tvposter/under-the-dome.jpg', 'lang' => 'en', 'likes' => '7'],
            ],
            'showbackground' => [
                ['id' => '33333', 'url' => 'https://assets.fanart.tv/fanart/tv/264492/showbackground/under-the-dome.jpg', 'lang' => '', 'likes' => '2'],
            ],
        ]),
    ]);

    $service = new FanartTVService;
    $artwork = $service->show(264492);

    expect($artwork)
        ->not->toBeNull()
        ->and($artwork['name'])->toBe('Under the Dome')
        ->and($artwork['thetvdb_id'])->toBe('264492')
        ->and($artwork['hdtvlogo'])->toHaveCount(1)
        ->and($artwork['tvposter'])->toHaveCount(1)
        ->and($artwork['showbackground'])->toHaveCount(1);
});

it('returns null when tv show has no artwork', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/9999999' => Http::response([], 404),
    ]);

    $service = new FanartTVService;
    $artwork = $service->show(9999999);

    expect($artwork)->toBeNull();
});

it('sends api key header with requests', function () {
    config(['services.fanart.api_key' => 'test-api-key']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/*' => Http::response([
            'name' => 'Test Movie',
            'tmdb_id' => '1',
            'imdb_id' => 'tt0000001',
        ]),
    ]);

    $service = new FanartTVService;
    $service->movie('tt0000001');

    Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-api-key'));
});

it('returns best image preferring english with highest likes', function () {
    $service = new FanartTVService;

    $images = [
        ['id' => '1', 'url' => 'https://example.com/1.jpg', 'lang' => 'de', 'likes' => '20'],
        ['id' => '2', 'url' => 'https://example.com/2.jpg', 'lang' => 'en', 'likes' => '5'],
        ['id' => '3', 'url' => 'https://example.com/3.jpg', 'lang' => 'en', 'likes' => '15'],
        ['id' => '4', 'url' => 'https://example.com/4.jpg', 'lang' => null, 'likes' => '10'],
    ];

    $best = $service->bestImage($images);

    expect($best['id'])->toBe('3');
});

it('returns best image with null language when no english available', function () {
    $service = new FanartTVService;

    $images = [
        ['id' => '1', 'url' => 'https://example.com/1.jpg', 'lang' => 'de', 'likes' => '20'],
        ['id' => '2', 'url' => 'https://example.com/2.jpg', 'lang' => null, 'likes' => '5'],
        ['id' => '3', 'url' => 'https://example.com/3.jpg', 'lang' => '', 'likes' => '10'],
    ];

    $best = $service->bestImage($images);

    expect($best['id'])->toBe('3');
});

it('returns null when no english or null language images exist', function () {
    $service = new FanartTVService;

    $images = [
        ['id' => '1', 'url' => 'https://example.com/1.jpg', 'lang' => 'de', 'likes' => '20'],
        ['id' => '2', 'url' => 'https://example.com/2.jpg', 'lang' => 'fr', 'likes' => '15'],
    ];

    $best = $service->bestImage($images);

    expect($best)->toBeNull();
});

it('fetches latest movies since timestamp', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt0111161'],
            ['imdb_id' => 'tt0068646'],
            ['imdb_id' => 'tt0071562'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestMovies(1704067200);

    expect($ids)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain('tt0111161', 'tt0068646', 'tt0071562');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'date=1704067200'));
});

it('fetches latest movies without timestamp', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest' => Http::response([
            ['imdb_id' => 'tt0111161'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestMovies();

    expect($ids)->toHaveCount(1);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'date='));
});

it('returns empty array when latest movies fails', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([], 500),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestMovies();

    expect($ids)->toBeArray()->toBeEmpty();
});

it('filters out null imdb ids from latest movies', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt0111161'],
            ['imdb_id' => null],
            ['imdb_id' => 'tt0068646'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestMovies();

    expect($ids)->toHaveCount(2)->not->toContain(null);
});

it('fetches latest shows since timestamp', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([
            ['thetvdb_id' => '264492'],
            ['thetvdb_id' => '121361'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestShows(1704067200);

    expect($ids)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain(264492, 121361);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'date=1704067200'));
});

it('fetches latest shows without timestamp', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/latest' => Http::response([
            ['thetvdb_id' => '264492'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestShows();

    expect($ids)->toHaveCount(1);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'date='));
});

it('returns empty array when latest shows fails', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([], 500),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestShows();

    expect($ids)->toBeArray()->toBeEmpty();
});

it('filters out null tvdb ids from latest shows', function () {
    Http::fake([
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([
            ['thetvdb_id' => '264492'],
            ['thetvdb_id' => null],
            ['thetvdb_id' => '121361'],
        ]),
    ]);

    $service = new FanartTVService;
    $ids = $service->latestShows();

    expect($ids)->toHaveCount(2)->not->toContain(null);
});
