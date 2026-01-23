<?php

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;

it('can be associated with a movie', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/shawshank.png',
        'lang' => 'en',
        'likes' => 5,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->mediable)->toBeInstanceOf(Movie::class)
        ->and($media->mediable->id)->toBe($movie->id)
        ->and($movie->media)->toHaveCount(1);
});

it('can be associated with a show', function () {
    $show = Show::factory()->create();

    $media = $show->media()->create([
        'fanart_id' => '67890',
        'type' => 'tvposter',
        'url' => 'https://assets.fanart.tv/fanart/tv/264492/tvposter/under-the-dome.jpg',
        'lang' => 'en',
        'likes' => 10,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->mediable)->toBeInstanceOf(Show::class)
        ->and($media->mediable->id)->toBe($show->id)
        ->and($show->media)->toHaveCount(1);
});

it('can store season-specific artwork', function () {
    $show = Show::factory()->create();

    $media = $show->media()->create([
        'fanart_id' => '11111',
        'type' => 'seasonposter',
        'url' => 'https://assets.fanart.tv/fanart/tv/264492/seasonposter/season01.jpg',
        'lang' => 'en',
        'likes' => 3,
        'season' => 1,
    ]);

    expect($media->season)->toBe(1);
});

it('can store disc artwork with disc metadata', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'fanart_id' => '22222',
        'type' => 'moviedisc',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/moviedisc/shawshank.png',
        'lang' => 'en',
        'likes' => 2,
        'disc' => '1',
        'disc_type' => 'bluray',
    ]);

    expect($media->disc)->toBe('1')
        ->and($media->disc_type)->toBe('bluray');
});

it('casts likes to integer', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'fanart_id' => '33333',
        'type' => 'movieposter',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/movieposter/shawshank.jpg',
        'likes' => '15',
    ]);

    expect($media->likes)->toBe(15)
        ->and($media->likes)->toBeInt();
});

it('allows null lang for textless images', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'fanart_id' => '44444',
        'type' => 'moviebackground',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/moviebackground/shawshank.jpg',
        'lang' => null,
        'likes' => 8,
    ]);

    expect($media->lang)->toBeNull();
});

it('prevents duplicate fanart_id for same mediable', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'fanart_id' => '55555',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/first.png',
        'likes' => 5,
    ]);

    expect(fn () => $movie->media()->create([
        'fanart_id' => '55555',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/duplicate.png',
        'likes' => 3,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('allows same fanart_id for different mediables', function () {
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();

    $media1 = $movie1->media()->create([
        'fanart_id' => '66666',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/1/hdmovielogo/logo.png',
        'likes' => 5,
    ]);

    $media2 = $movie2->media()->create([
        'fanart_id' => '66666',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/2/hdmovielogo/logo.png',
        'likes' => 3,
    ]);

    expect($media1)->toBeInstanceOf(Media::class)
        ->and($media2)->toBeInstanceOf(Media::class);
});
