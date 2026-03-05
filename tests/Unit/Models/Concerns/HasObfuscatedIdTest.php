<?php

use App\Models\Movie;
use App\Models\Show;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a sqid string from getRouteKey', function () {
    $movie = Movie::factory()->create();

    $routeKey = $movie->getRouteKey();

    expect($routeKey)->toBeString()
        ->and(Sqid::decode($routeKey))->toBe($movie->id);
});

it('provides a sqid accessor on the model', function () {
    $show = Show::factory()->create();

    expect($show->sqid)->toBe(Sqid::encode($show->id));
});

it('resolves route binding from a valid sqid', function () {
    $movie = Movie::factory()->create();
    $sqid = Sqid::encode($movie->id);

    $resolved = (new Movie)->resolveRouteBinding($sqid);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($movie->id);
});

it('returns null from resolveRouteBinding for an invalid sqid', function () {
    $resolved = (new Movie)->resolveRouteBinding('!!!invalid!!!');

    expect($resolved)->toBeNull();
});

it('returns null from resolveRouteBinding for a non-existent id', function () {
    $sqid = Sqid::encode(99999);

    $resolved = (new Movie)->resolveRouteBinding($sqid);

    expect($resolved)->toBeNull();
});

it('resolves show route binding with eager-loaded episodes', function () {
    $show = Show::factory()->create();
    $sqid = Sqid::encode($show->id);

    $resolved = (new Show)->resolveRouteBinding($sqid);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($show->id)
        ->and($resolved->relationLoaded('episodes'))->toBeTrue();
});

it('does not resolve with a raw integer id', function () {
    $movie = Movie::factory()->create();

    $resolved = (new Movie)->resolveRouteBinding((string) $movie->id);

    expect($resolved)->toBeNull();
});
