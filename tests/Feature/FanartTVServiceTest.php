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
