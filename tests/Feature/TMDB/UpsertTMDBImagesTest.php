<?php

use App\Actions\TMDB\UpsertTMDBImages;
use App\Enums\ArtworkType;
use App\Models\Movie;
use App\Models\Show;

it('stores poster, backdrop, and logo images', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/poster1.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.5, 'vote_count' => 10, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [
            ['file_path' => '/backdrop1.jpg', 'iso_639_1' => null, 'vote_average' => 8.0, 'vote_count' => 50, 'width' => 1920, 'height' => 1080],
        ],
        'logos' => [
            ['file_path' => '/logo1.png', 'iso_639_1' => 'en', 'vote_average' => 6.0, 'vote_count' => 5, 'width' => 500, 'height' => 200],
        ],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    expect($movie->media)->toHaveCount(3)
        ->and($movie->media()->where('type', ArtworkType::Poster->value)->count())->toBe(1)
        ->and($movie->media()->where('type', ArtworkType::Backdrop->value)->count())->toBe(1)
        ->and($movie->media()->where('type', ArtworkType::Logo->value)->count())->toBe(1);
});

it('selects best english image as active per type', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/poster_low.jpg', 'iso_639_1' => 'en', 'vote_average' => 3.0, 'vote_count' => 5, 'width' => 500, 'height' => 750],
            ['file_path' => '/poster_high.jpg', 'iso_639_1' => 'en', 'vote_average' => 8.0, 'vote_count' => 50, 'width' => 500, 'height' => 750],
            ['file_path' => '/poster_de.jpg', 'iso_639_1' => 'de', 'vote_average' => 9.0, 'vote_count' => 100, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    $active = $movie->media()->where('is_active', true)->first();

    expect($active->file_path)->toBe('/poster_high.jpg')
        ->and($movie->media()->where('is_active', false)->count())->toBe(2);
});

it('prefers null language images when no english images exist', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [],
        'backdrops' => [
            ['file_path' => '/backdrop_null.jpg', 'iso_639_1' => null, 'vote_average' => 7.0, 'vote_count' => 30, 'width' => 1920, 'height' => 1080],
            ['file_path' => '/backdrop_de.jpg', 'iso_639_1' => 'de', 'vote_average' => 9.0, 'vote_count' => 100, 'width' => 1920, 'height' => 1080],
        ],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    $active = $movie->media()->where('is_active', true)->first();

    expect($active->file_path)->toBe('/backdrop_null.jpg');
});

it('prefers textless backdrops over english backdrops', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [],
        'backdrops' => [
            ['file_path' => '/backdrop_en.jpg', 'iso_639_1' => 'en', 'vote_average' => 9.0, 'vote_count' => 100, 'width' => 1920, 'height' => 1080],
            ['file_path' => '/backdrop_textless.jpg', 'iso_639_1' => null, 'vote_average' => 2.0, 'vote_count' => 3, 'width' => 1920, 'height' => 1080],
        ],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    $active = $movie->media()->where('is_active', true)->first();

    expect($active->file_path)->toBe('/backdrop_textless.jpg');
});

it('does not prefer textless for posters', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/poster_en.jpg', 'iso_639_1' => 'en', 'vote_average' => 9.0, 'vote_count' => 100, 'width' => 500, 'height' => 750],
            ['file_path' => '/poster_null.jpg', 'iso_639_1' => null, 'vote_average' => 2.0, 'vote_count' => 3, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    $active = $movie->media()->where('is_active', true)->first();

    expect($active->file_path)->toBe('/poster_en.jpg');
});

it('uses vote_count as tiebreaker when vote_average is equal', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/poster_low_count.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 5, 'width' => 500, 'height' => 750],
            ['file_path' => '/poster_high_count.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 50, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    $active = $movie->media()->where('is_active', true)->first();

    expect($active->file_path)->toBe('/poster_high_count.jpg');
});

it('is idempotent on re-run', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/poster1.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.5, 'vote_count' => 10, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    $upsert = app(UpsertTMDBImages::class);
    $upsert->upsert($movie, $imagesResponse);
    $upsert->upsert($movie, $imagesResponse);

    expect($movie->media()->count())->toBe(1);
});

it('updates existing records on re-run with new data', function () {
    $movie = Movie::factory()->create();

    $upsert = app(UpsertTMDBImages::class);

    $upsert->upsert($movie, [
        'posters' => [
            ['file_path' => '/poster1.jpg', 'iso_639_1' => 'en', 'vote_average' => 3.0, 'vote_count' => 5, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ]);

    $upsert->upsert($movie, [
        'posters' => [
            ['file_path' => '/poster1.jpg', 'iso_639_1' => 'en', 'vote_average' => 8.0, 'vote_count' => 50, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ]);

    $media = $movie->media()->first();

    expect($movie->media()->count())->toBe(1)
        ->and($media->vote_average)->toBe(8.0)
        ->and($media->vote_count)->toBe(50);
});

it('deactivates previous active media before setting a new active image', function () {
    $movie = Movie::factory()->create();

    $upsert = app(UpsertTMDBImages::class);

    $upsert->upsert($movie, [
        'posters' => [
            ['file_path' => '/old-poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 10, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ]);

    $upsert->upsert($movie, [
        'posters' => [
            ['file_path' => '/new-poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 8.0, 'vote_count' => 50, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ]);

    expect($movie->media()->where('type', ArtworkType::Poster->value)->where('is_active', true)->count())->toBe(1)
        ->and($movie->media()->where('file_path', '/old-poster.jpg')->first()?->is_active)->toBeFalse()
        ->and($movie->media()->where('file_path', '/new-poster.jpg')->first()?->is_active)->toBeTrue();
});

it('deactivates missing artwork types on re-run', function () {
    $movie = Movie::factory()->create();

    $upsert = app(UpsertTMDBImages::class);

    $upsert->upsert($movie, [
        'posters' => [],
        'backdrops' => [],
        'logos' => [
            ['file_path' => '/logo.jpg', 'iso_639_1' => 'en', 'vote_average' => 6.0, 'vote_count' => 5, 'width' => 500, 'height' => 200],
        ],
    ]);

    $upsert->upsert($movie, [
        'posters' => [],
        'backdrops' => [],
        'logos' => [],
    ]);

    expect($movie->media()->where('type', ArtworkType::Logo->value)->where('is_active', true)->count())->toBe(0)
        ->and($movie->media()->where('file_path', '/logo.jpg')->first()?->is_active)->toBeFalse();
});

it('works for shows', function () {
    $show = Show::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['file_path' => '/show_poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 7.0, 'vote_count' => 25, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($show, $imagesResponse);

    expect($show->media)->toHaveCount(1)
        ->and($show->media()->first()->type)->toBe(ArtworkType::Poster);
});

it('skips images without file_path', function () {
    $movie = Movie::factory()->create();

    $imagesResponse = [
        'posters' => [
            ['iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 10, 'width' => 500, 'height' => 750],
            ['file_path' => '/valid.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 10, 'width' => 500, 'height' => 750],
        ],
        'backdrops' => [],
        'logos' => [],
    ];

    app(UpsertTMDBImages::class)->upsert($movie, $imagesResponse);

    expect($movie->media()->count())->toBe(1);
});

it('handles empty images response', function () {
    $movie = Movie::factory()->create();

    app(UpsertTMDBImages::class)->upsert($movie, [
        'posters' => [],
        'backdrops' => [],
        'logos' => [],
    ]);

    expect($movie->media()->count())->toBe(0);
});
