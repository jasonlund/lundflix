<?php

use App\Enums\ArtworkType;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;

it('can be associated with a movie', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Poster->value,
        'lang' => 'en',
        'vote_average' => 5.5,
        'vote_count' => 10,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->mediable)->toBeInstanceOf(Movie::class)
        ->and($media->mediable->id)->toBe($movie->id)
        ->and($movie->media)->toHaveCount(1);
});

it('can be associated with a show', function () {
    $show = Show::factory()->create();

    $media = $show->media()->create([
        'file_path' => '/show_poster.jpg',
        'type' => ArtworkType::Poster->value,
        'lang' => 'en',
        'vote_average' => 7.0,
        'vote_count' => 25,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->mediable)->toBeInstanceOf(Show::class)
        ->and($media->mediable->id)->toBe($show->id)
        ->and($show->media)->toHaveCount(1);
});

it('constructs tmdb cdn url with default size', function () {
    $media = Media::factory()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Poster->value,
    ]);

    expect($media->url())->toBe('https://image.tmdb.org/t/p/w780/abc123.jpg');
});

it('constructs tmdb cdn url with custom size', function () {
    $media = Media::factory()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Backdrop->value,
    ]);

    expect($media->url('w500'))->toBe('https://image.tmdb.org/t/p/w500/abc123.jpg');
});

it('constructs original url', function () {
    $media = Media::factory()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Logo->value,
    ]);

    expect($media->originalUrl())->toBe('https://image.tmdb.org/t/p/original/abc123.jpg');
});

it('uses correct default size per artwork type', function (ArtworkType $type, string $expectedSize) {
    $media = Media::factory()->create([
        'file_path' => '/test.jpg',
        'type' => $type->value,
    ]);

    expect($media->url())->toBe("https://image.tmdb.org/t/p/{$expectedSize}/test.jpg");
})->with([
    'poster' => [ArtworkType::Poster, 'w780'],
    'backdrop' => [ArtworkType::Backdrop, 'w1280'],
    'logo' => [ArtworkType::Logo, 'w500'],
]);

it('allows null lang for textless images', function () {
    $movie = Movie::factory()->create();

    $media = $movie->media()->create([
        'file_path' => '/textless.jpg',
        'type' => ArtworkType::Backdrop->value,
        'lang' => null,
        'vote_average' => 8.0,
        'vote_count' => 50,
    ]);

    expect($media->lang)->toBeNull();
});

it('prevents duplicate file_path for same mediable', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Poster->value,
        'vote_average' => 5.0,
        'vote_count' => 10,
    ]);

    expect(fn () => $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Poster->value,
        'vote_average' => 3.0,
        'vote_count' => 5,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('allows same file_path for different mediables', function () {
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();

    $media1 = $movie1->media()->create([
        'file_path' => '/shared.jpg',
        'type' => ArtworkType::Poster->value,
        'vote_average' => 5.0,
        'vote_count' => 10,
    ]);

    $media2 = $movie2->media()->create([
        'file_path' => '/shared.jpg',
        'type' => ArtworkType::Poster->value,
        'vote_average' => 3.0,
        'vote_count' => 5,
    ]);

    expect($media1)->toBeInstanceOf(Media::class)
        ->and($media2)->toBeInstanceOf(Media::class);
});

it('casts type to ArtworkType enum', function () {
    $media = Media::factory()->create([
        'type' => ArtworkType::Poster->value,
    ]);

    expect($media->type)->toBe(ArtworkType::Poster);
});
