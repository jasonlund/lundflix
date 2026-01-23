<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;

it('stores movie artwork from api response', function () {
    $movie = Movie::factory()->create();

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

    expect($movie->media)->toHaveCount(3)
        ->and($movie->media()->where('type', 'hdmovielogo')->count())->toBe(2)
        ->and($movie->media()->where('type', 'movieposter')->count())->toBe(1);
});

it('stores show artwork with season information', function () {
    $show = Show::factory()->create();

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

    expect($show->media)->toHaveCount(3)
        ->and($seasonPosters)->toHaveCount(2)
        ->and($seasonPosters->firstWhere('fanart_id', '22222')->season)->toBe(1)
        ->and($seasonPosters->firstWhere('fanart_id', '22223')->season)->toBe(2);
});

it('stores movie disc artwork with disc metadata', function () {
    $movie = Movie::factory()->create();

    $response = [
        'moviedisc' => [
            ['id' => '33333', 'url' => 'https://assets.fanart.tv/disc.png', 'lang' => 'en', 'likes' => '2', 'disc' => '1', 'disc_type' => 'bluray'],
        ],
    ];

    StoreFanart::dispatchSync($movie, $response);

    $disc = $movie->media()->where('type', 'moviedisc')->first();

    expect($disc->disc)->toBe('1')
        ->and($disc->disc_type)->toBe('bluray');
});

it('updates existing media on re-run', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/old.png',
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
});
