<?php

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('can activate a media item', function () {
    $movie = Movie::factory()->create();
    $media = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12345.jpg',
        'is_active' => false,
    ]);

    $media->activate();

    expect($media->fresh()->is_active)->toBeTrue();
});

it('can deactivate a media item', function () {
    $movie = Movie::factory()->create();
    $media = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'is_active' => true,
    ]);

    $media->deactivate();

    expect($media->fresh()->is_active)->toBeFalse();
});

it('deactivates siblings when activating for same type', function () {
    $movie = Movie::factory()->create();

    $media1 = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12345.jpg',
        'is_active' => true,
    ]);
    $media2 = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12346.jpg',
        'is_active' => false,
    ]);

    $media2->activate();

    expect($media1->fresh()->is_active)->toBeFalse()
        ->and($media2->fresh()->is_active)->toBeTrue();
});

it('does not deactivate media of different types', function () {
    $movie = Movie::factory()->create();

    $poster = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12345.jpg',
        'is_active' => true,
    ]);
    $logo = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'hdmovielogo',
        'path' => 'fanart/movie/1/hdmovielogo/12346.png',
        'is_active' => true,
    ]);

    $newPoster = Media::factory()->for($movie, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12347.jpg',
        'is_active' => false,
    ]);

    $newPoster->activate();

    expect($poster->fresh()->is_active)->toBeFalse()
        ->and($logo->fresh()->is_active)->toBeTrue()
        ->and($newPoster->fresh()->is_active)->toBeTrue();
});

it('does not deactivate media of different mediables', function () {
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();

    $media1 = Media::factory()->for($movie1, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/1/movieposter/12345.jpg',
        'is_active' => true,
    ]);
    $media2 = Media::factory()->for($movie2, 'mediable')->create([
        'type' => 'movieposter',
        'path' => 'fanart/movie/2/movieposter/12346.jpg',
        'is_active' => false,
    ]);

    $media2->activate();

    expect($media1->fresh()->is_active)->toBeTrue()
        ->and($media2->fresh()->is_active)->toBeTrue();
});

it('allows one active per type per season for shows', function () {
    $show = Show::factory()->create();

    $season1Poster1 = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'seasonposter',
        'path' => 'fanart/show/1/seasonposter/12345.jpg',
        'season' => 1,
        'is_active' => true,
    ]);
    $season1Poster2 = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'seasonposter',
        'path' => 'fanart/show/1/seasonposter/12346.jpg',
        'season' => 1,
        'is_active' => false,
    ]);
    $season2Poster = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'seasonposter',
        'path' => 'fanart/show/1/seasonposter/12347.jpg',
        'season' => 2,
        'is_active' => true,
    ]);

    $season1Poster2->activate();

    expect($season1Poster1->fresh()->is_active)->toBeFalse()
        ->and($season1Poster2->fresh()->is_active)->toBeTrue()
        ->and($season2Poster->fresh()->is_active)->toBeTrue();
});

it('treats all seasons as separate from specific seasons', function () {
    $show = Show::factory()->create();

    $allSeasonsPoster = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'seasonposter',
        'path' => 'fanart/show/1/seasonposter/12345.jpg',
        'season' => 0,
        'is_active' => true,
    ]);
    $season1Poster = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'seasonposter',
        'path' => 'fanart/show/1/seasonposter/12346.jpg',
        'season' => 1,
        'is_active' => false,
    ]);

    $season1Poster->activate();

    expect($allSeasonsPoster->fresh()->is_active)->toBeTrue()
        ->and($season1Poster->fresh()->is_active)->toBeTrue();
});

it('downloads and stores image when activating media without path', function () {
    Http::preventStrayRequests();
    Storage::fake();

    Http::fake([
        'assets.fanart.tv/*' => Http::response('fake-image-content'),
    ]);

    $movie = Movie::factory()->create();
    $media = Media::factory()->for($movie, 'mediable')->create([
        'fanart_id' => '12345',
        'type' => 'movieposter',
        'url' => 'https://assets.fanart.tv/poster.jpg',
        'path' => null,
        'is_active' => false,
    ]);

    $media->activate();

    $expectedPath = "fanart/movie/{$movie->id}/movieposter/12345.jpg";

    expect($media->fresh()->path)->toBe($expectedPath)
        ->and($media->fresh()->is_active)->toBeTrue();

    Storage::assertExists($expectedPath);
});

it('does not re-download image when activating media with existing path', function () {
    Http::preventStrayRequests();
    Storage::fake();

    $movie = Movie::factory()->create();
    $existingPath = "fanart/movie/{$movie->id}/movieposter/12345.jpg";

    Storage::put($existingPath, 'existing-content');

    $media = Media::factory()->for($movie, 'mediable')->create([
        'fanart_id' => '12345',
        'type' => 'movieposter',
        'url' => 'https://assets.fanart.tv/poster.jpg',
        'path' => $existingPath,
        'is_active' => false,
    ]);

    $media->activate();

    expect($media->fresh()->path)->toBe($existingPath)
        ->and($media->fresh()->is_active)->toBeTrue();

    // Verify no HTTP requests were made
    Http::assertNothingSent();
});
