<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake();
});

it('stores movie artwork from api response', function () {
    $movie = Movie::factory()->create();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('fake-image-content'),
    ]);

    $response = [
        'hdmovielogo' => [
            ['id' => '12345', 'url' => 'https://assets.fanart.tv/logo1.png', 'lang' => 'en', 'likes' => '5'],
            ['id' => '12346', 'url' => 'https://assets.fanart.tv/logo2.png', 'lang' => 'de', 'likes' => '3'],
        ],
        'movieposter' => [
            ['id' => '67890', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '10'],
        ],
    ];

    StoreFanart::dispatchSync($movie, $response);

    // All records stored in DB
    expect($movie->media)->toHaveCount(3)
        ->and($movie->media()->where('type', 'hdmovielogo')->count())->toBe(2)
        ->and($movie->media()->where('type', 'movieposter')->count())->toBe(1);

    // Only best English image per type downloaded
    Storage::assertExists("fanart/movie/{$movie->id}/hdmovielogo/12345.png"); // English with highest likes
    Storage::assertMissing("fanart/movie/{$movie->id}/hdmovielogo/12346.png"); // German, not downloaded
    Storage::assertExists("fanart/movie/{$movie->id}/movieposter/67890.jpg");

    // Non-downloaded images have null path and are not active
    $bestLogo = $movie->media()->where('fanart_id', '12345')->first();
    $otherLogo = $movie->media()->where('fanart_id', '12346')->first();

    expect($bestLogo->path)->not->toBeNull()
        ->and($bestLogo->is_active)->toBeTrue()
        ->and($otherLogo->path)->toBeNull()
        ->and($otherLogo->is_active)->toBeFalse();
});

it('stores show artwork with season information', function () {
    $show = Show::factory()->create();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('fake-image-content'),
    ]);

    $response = [
        'tvposter' => [
            ['id' => '11111', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '5'],
        ],
        'seasonposter' => [
            ['id' => '22222', 'url' => 'https://assets.fanart.tv/season1.jpg', 'lang' => 'en', 'likes' => '3', 'season' => '1'],
            ['id' => '22223', 'url' => 'https://assets.fanart.tv/season2.jpg', 'lang' => 'en', 'likes' => '2', 'season' => '2'],
        ],
    ];

    StoreFanart::dispatchSync($show, $response);

    $seasonPosters = $show->media()->where('type', 'seasonposter')->get();

    // All records stored in DB with season metadata
    expect($show->media)->toHaveCount(3)
        ->and($seasonPosters)->toHaveCount(2)
        ->and($seasonPosters->firstWhere('fanart_id', '22222')->season)->toBe(1)
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->season)->toBe(2);

    // Best image per type per season downloaded and marked active
    Storage::assertExists("fanart/show/{$show->id}/tvposter/11111.jpg");
    Storage::assertExists("fanart/show/{$show->id}/seasonposter/22222.jpg"); // Best for season 1
    Storage::assertExists("fanart/show/{$show->id}/seasonposter/22223.jpg"); // Best for season 2

    // Best images are marked as active
    expect($seasonPosters->firstWhere('fanart_id', '22222')->is_active)->toBeTrue()
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->is_active)->toBeTrue();
});

it('stores movie disc artwork with disc metadata', function () {
    $movie = Movie::factory()->create();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('fake-image-content'),
    ]);

    $response = [
        'moviedisc' => [
            ['id' => '33333', 'url' => 'https://assets.fanart.tv/disc.png', 'lang' => 'en', 'likes' => '2', 'disc' => '1', 'disc_type' => 'bluray'],
        ],
    ];

    StoreFanart::dispatchSync($movie, $response);

    $disc = $movie->media()->where('type', 'moviedisc')->first();

    expect($disc->disc)->toBe('1')
        ->and($disc->disc_type)->toBe('bluray')
        ->and($disc->path)->toBe("fanart/movie/{$movie->id}/moviedisc/33333.png");

    Storage::assertExists("fanart/movie/{$movie->id}/moviedisc/33333.png");
});

it('updates existing media on re-run', function () {
    $movie = Movie::factory()->create();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('new-image-content'),
    ]);

    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/old.png',
        'path' => "fanart/movie/{$movie->id}/hdmovielogo/12345.png",
        'likes' => 5,
    ]);

    $response = [
        'hdmovielogo' => [
            ['id' => '12345', 'url' => 'https://assets.fanart.tv/new.png', 'lang' => 'en', 'likes' => '10'],
        ],
    ];

    StoreFanart::dispatchSync($movie, $response);

    expect($movie->media)->toHaveCount(1)
        ->and($movie->media->first()->url)->toBe('https://assets.fanart.tv/new.png')
        ->and($movie->media->first()->likes)->toBe(10);

    Storage::assertExists("fanart/movie/{$movie->id}/hdmovielogo/12345.png");
});

it('stores all-season artwork with season value of zero', function () {
    $show = Show::factory()->create();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('fake-image-content'),
    ]);

    $response = [
        'showbackground' => [
            ['id' => '44444', 'url' => 'https://assets.fanart.tv/bg.jpg', 'lang' => 'en', 'likes' => '5', 'season' => 'all'],
        ],
        'seasonposter' => [
            ['id' => '55555', 'url' => 'https://assets.fanart.tv/season1.jpg', 'lang' => 'en', 'likes' => '3', 'season' => '1'],
        ],
        'hdtvlogo' => [
            ['id' => '66666', 'url' => 'https://assets.fanart.tv/logo.png', 'lang' => 'en', 'likes' => '2'],
        ],
    ];

    StoreFanart::dispatchSync($show, $response);

    $background = $show->media()->where('type', 'showbackground')->first();
    $seasonPoster = $show->media()->where('type', 'seasonposter')->first();
    $logo = $show->media()->where('type', 'hdtvlogo')->first();

    expect($background->season)->toBe(0) // 'all' converted to 0
        ->and($seasonPoster->season)->toBe(1) // numeric string converted to int
        ->and($logo->season)->toBeNull(); // null remains null
});

it('cleans up stored files on failure', function () {
    $movie = Movie::factory()->create();

    Http::fake([
        'assets.fanart.tv/logo1.png' => Http::response('image-content-1'),
        'assets.fanart.tv/poster.jpg' => Http::response('', 500), // Best poster fails
    ]);

    $response = [
        'hdmovielogo' => [
            ['id' => '12345', 'url' => 'https://assets.fanart.tv/logo1.png', 'lang' => 'en', 'likes' => '5'],
        ],
        'movieposter' => [
            ['id' => '67890', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '10'],
        ],
    ];

    try {
        StoreFanart::dispatchSync($movie, $response);
    } catch (Throwable) {
        // Expected
    }

    // First image was stored but should be cleaned up on failure
    Storage::assertMissing("fanart/movie/{$movie->id}/hdmovielogo/12345.png");
    expect($movie->media()->count())->toBe(0);
});
