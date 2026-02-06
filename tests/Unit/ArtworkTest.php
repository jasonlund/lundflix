<?php

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('allows fetching art when no missing cache keys exist', function (string $mediable, callable $factory): void {
    Cache::flush();

    $model = $factory();
    $expectedUrl = route('art', ['mediable' => $mediable, 'id' => $model->id, 'type' => 'poster']);

    expect($model->canFetchArt('poster'))->toBeTrue()
        ->and($model->artUrl('poster'))->toBe($expectedUrl);
})->with([
    'show' => ['show', fn (): Show => Show::factory()->create(['thetvdb_id' => 123456])],
    'movie' => ['movie', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt9000001'])],
]);

it('blocks fetching art when the base missing cache key exists', function (callable $factory): void {
    Cache::flush();

    $model = $factory();

    Cache::put($model->artMissingCacheKey(), true, now()->addHour());

    expect($model->canFetchArt('poster'))->toBeFalse()
        ->and($model->artUrl('poster'))->toBeNull();
})->with([
    'show' => fn (): Show => Show::factory()->create(['thetvdb_id' => 123456]),
    'movie' => fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt9000002']),
]);

it('blocks fetching art when the type missing cache key exists', function (callable $factory): void {
    Cache::flush();

    $model = $factory();

    Cache::put($model->artMissingTypeCacheKey('poster'), true, now()->addHour());

    expect($model->canFetchArt('poster'))->toBeFalse()
        ->and($model->artUrl('poster'))->toBeNull();
})->with([
    'show' => fn (): Show => Show::factory()->create(['thetvdb_id' => 123456]),
    'movie' => fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt9000003']),
]);
