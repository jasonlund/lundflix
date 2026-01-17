<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    session()->flush();
});

it('starts with empty cart', function () {
    $cart = new CartService;

    expect($cart->count())->toBe(0)
        ->and($cart->isEmpty())->toBeTrue();
});

it('can add a movie to cart', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->add($movie);

    expect($cart->count())->toBe(1)
        ->and($cart->has($movie))->toBeTrue();
});

it('can add an episode to cart', function () {
    $cart = new CartService;
    $episode = Episode::factory()->create();

    $cart->add($episode);

    expect($cart->count())->toBe(1)
        ->and($cart->has($episode))->toBeTrue();
});

it('can remove item from cart', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->add($movie);
    $cart->remove($movie);

    expect($cart->count())->toBe(0)
        ->and($cart->has($movie))->toBeFalse();
});

it('does not add duplicate items', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->add($movie);
    $cart->add($movie);

    expect($cart->count())->toBe(1);
});

it('can load items with models', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();
    $episode = Episode::factory()->create();

    $cart->add($movie);
    $cart->add($episode);

    $items = $cart->loadItems();

    expect($items)->toHaveCount(2);
});

it('can clear all items', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->add($movie);
    $cart->clear();

    expect($cart->isEmpty())->toBeTrue();
});

it('returns items array structure', function () {
    $cart = new CartService;

    $items = $cart->items();

    expect($items)->toHaveKeys(['movies', 'episodes']);
});

it('can mix movies and episodes in cart', function () {
    $cart = new CartService;
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();
    $episode = Episode::factory()->create();

    $cart->add($movie1);
    $cart->add($movie2);
    $cart->add($episode);

    expect($cart->count())->toBe(3)
        ->and($cart->has($movie1))->toBeTrue()
        ->and($cart->has($movie2))->toBeTrue()
        ->and($cart->has($episode))->toBeTrue();
});

it('can add a special episode to cart', function () {
    $cart = new CartService;
    $episode = Episode::factory()->special()->create([
        'season' => 24,
        'number' => 1,
    ]);

    $cart->add($episode);

    expect($cart->count())->toBe(1)
        ->and($cart->has($episode))->toBeTrue();

    $items = $cart->items();
    expect($items['episodes'][0]['code'])->toBe('s24s01');
});

it('can add episode via array with type', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $cart->add([
        'show_id' => $show->id,
        'season' => 24,
        'number' => 1,
        'type' => 'significant_special',
    ]);

    expect($cart->count())->toBe(1);

    $items = $cart->items();
    expect($items['episodes'][0]['code'])->toBe('s24s01');
});

it('distinguishes between regular and special episodes with same season and number', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $regularEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 24,
        'number' => 1,
        'type' => 'regular',
    ]);

    $specialEpisode = Episode::factory()->special()->create([
        'show_id' => $show->id,
        'season' => 24,
        'number' => 1,
    ]);

    $cart->add($regularEpisode);
    $cart->add($specialEpisode);

    expect($cart->count())->toBe(2)
        ->and($cart->has($regularEpisode))->toBeTrue()
        ->and($cart->has($specialEpisode))->toBeTrue();

    $items = $cart->items();
    $codes = array_column($items['episodes'], 'code');
    expect($codes)->toContain('s24e01')
        ->and($codes)->toContain('s24s01');
});

it('loads special episodes correctly', function () {
    $cart = new CartService;
    $episode = Episode::factory()->special()->create([
        'season' => 24,
        'number' => 1,
    ]);

    $cart->add($episode);
    $loaded = $cart->loadItems();

    expect($loaded)->toHaveCount(1)
        ->and($loaded->first()->id)->toBe($episode->id)
        ->and($loaded->first()->isSpecial())->toBeTrue();
});
