<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('stores movie artwork from api response', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/logo1.png', 'lang' => 'en', 'likes' => '5'],
                ['id' => '12346', 'url' => 'https://assets.fanart.tv/logo2.png', 'lang' => 'de', 'likes' => '3'],
            ],
            'movieposter' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    StoreFanart::dispatchSync($movie);

    // All records stored in DB
    expect($movie->media)->toHaveCount(3)
        ->and($movie->media()->where('type', 'hdmovielogo')->count())->toBe(2)
        ->and($movie->media()->where('type', 'movieposter')->count())->toBe(1);

    // Best English image per type is active; paths are not stored
    $bestLogo = $movie->media()->where('fanart_id', '12345')->first();
    $otherLogo = $movie->media()->where('fanart_id', '12346')->first();
    $poster = $movie->media()->where('fanart_id', '67890')->first();

    expect($bestLogo->path)->toBeNull()
        ->and($bestLogo->is_active)->toBeTrue()
        ->and($otherLogo->path)->toBeNull()
        ->and($otherLogo->is_active)->toBeFalse()
        ->and($poster->path)->toBeNull()
        ->and($poster->is_active)->toBeTrue();
});

it('stores show artwork with season information', function () {
    $show = Show::factory()->create(['thetvdb_id' => 264492]);

    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'tvposter' => [
                ['id' => '11111', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '5'],
            ],
            'seasonposter' => [
                ['id' => '22222', 'url' => 'https://assets.fanart.tv/season1.jpg', 'lang' => 'en', 'likes' => '3', 'season' => '1'],
                ['id' => '22223', 'url' => 'https://assets.fanart.tv/season2.jpg', 'lang' => 'en', 'likes' => '2', 'season' => '2'],
            ],
        ]),
    ]);

    StoreFanart::dispatchSync($show);

    $seasonPosters = $show->media()->where('type', 'seasonposter')->get();

    // All records stored in DB with season metadata
    expect($show->media)->toHaveCount(3)
        ->and($seasonPosters)->toHaveCount(2)
        ->and($seasonPosters->firstWhere('fanart_id', '22222')->season)->toBe(1)
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->season)->toBe(2);

    // Best images are marked as active
    expect($seasonPosters->firstWhere('fanart_id', '22222')->is_active)->toBeTrue()
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->is_active)->toBeTrue()
        ->and($seasonPosters->firstWhere('fanart_id', '22222')->path)->toBeNull()
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->path)->toBeNull();
});

it('stores movie disc artwork with disc metadata', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'moviedisc' => [
                ['id' => '33333', 'url' => 'https://assets.fanart.tv/disc.png', 'lang' => 'en', 'likes' => '2', 'disc' => '1', 'disc_type' => 'bluray'],
            ],
        ]),
    ]);

    StoreFanart::dispatchSync($movie);

    $disc = $movie->media()->where('type', 'moviedisc')->first();

    expect($disc->disc)->toBe('1')
        ->and($disc->disc_type)->toBe('bluray')
        ->and($disc->path)->toBeNull();
});

it('updates existing media on re-run', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/old.png',
        'path' => "fanart/movie/{$movie->id}/hdmovielogo/12345.png",
        'likes' => 5,
    ]);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/new.png', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    StoreFanart::dispatchSync($movie);

    expect($movie->media)->toHaveCount(1)
        ->and($movie->media->first()->url)->toBe('https://assets.fanart.tv/new.png')
        ->and($movie->media->first()->likes)->toBe(10)
        ->and($movie->media->first()->path)->toBeNull()
        ->and($movie->media->first()->is_active)->toBeTrue();
});

it('stores all-season artwork with season value of zero', function () {
    $show = Show::factory()->create(['thetvdb_id' => 264492]);

    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'showbackground' => [
                ['id' => '44444', 'url' => 'https://assets.fanart.tv/bg.jpg', 'lang' => 'en', 'likes' => '5', 'season' => 'all'],
            ],
            'seasonposter' => [
                ['id' => '55555', 'url' => 'https://assets.fanart.tv/season1.jpg', 'lang' => 'en', 'likes' => '3', 'season' => '1'],
            ],
            'hdtvlogo' => [
                ['id' => '66666', 'url' => 'https://assets.fanart.tv/logo.png', 'lang' => 'en', 'likes' => '2'],
            ],
        ]),
    ]);

    StoreFanart::dispatchSync($show);

    $background = $show->media()->where('type', 'showbackground')->first();
    $seasonPoster = $show->media()->where('type', 'seasonposter')->first();
    $logo = $show->media()->where('type', 'hdtvlogo')->first();

    expect($background->season)->toBe(0) // 'all' converted to 0
        ->and($seasonPoster->season)->toBe(1) // numeric string converted to int
        ->and($logo->season)->toBeNull(); // null remains null
});

it('returns early when api returns no artwork', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    StoreFanart::dispatchSync($movie);

    expect($movie->media()->count())->toBe(0);
});
