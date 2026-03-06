<?php

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates art url when model has tmdb_id', function (string $mediable, callable $factory): void {
    $model = $factory();
    $expectedUrl = route('art', ['mediable' => $mediable, 'id' => $model->sqid, 'type' => 'poster']);

    expect($model->canHaveArt())->toBeTrue()
        ->and($model->artUrl('poster'))->toBe($expectedUrl);
})->with([
    'show' => ['show', fn (): Show => Show::factory()->create(['tmdb_id' => 12345])],
    'movie' => ['movie', fn (): Movie => Movie::factory()->create(['tmdb_id' => 67890])],
]);

it('returns null art url when model has no tmdb_id', function (callable $factory): void {
    $model = $factory();

    expect($model->canHaveArt())->toBeFalse()
        ->and($model->artUrl('poster'))->toBeNull();
})->with([
    'show' => fn (): Show => Show::factory()->create(['tmdb_id' => null]),
    'movie' => fn (): Movie => Movie::factory()->create(['tmdb_id' => null]),
]);
