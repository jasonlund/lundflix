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

    $cart->toggleMovie($movie->id);

    expect($cart->count())->toBe(1)
        ->and($cart->has($movie->id))->toBeTrue();
});

it('can add an episode to cart via sync', function () {
    $cart = new CartService;
    $episode = Episode::factory()->create();

    $cart->syncShowEpisodes($episode->show_id, [$episode->code]);

    expect($cart->count())->toBe(1)
        ->and($cart->has($episode))->toBeTrue();
});

it('can remove movie from cart via toggle', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->toggleMovie($movie->id); // add
    $cart->toggleMovie($movie->id); // remove

    expect($cart->count())->toBe(0)
        ->and($cart->has($movie->id))->toBeFalse();
});

it('toggleMovie toggles movie state', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    expect($cart->toggleMovie($movie->id))->toBeTrue(); // added
    expect($cart->toggleMovie($movie->id))->toBeFalse(); // removed
    expect($cart->count())->toBe(0);
});

it('can load items with models', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();
    $episode = Episode::factory()->create();

    $cart->toggleMovie($movie->id);
    $cart->syncShowEpisodes($episode->show_id, [$episode->code]);

    $items = $cart->loadItems();

    expect($items)->toHaveCount(2);
});

it('can clear all items', function () {
    $cart = new CartService;
    $movie = Movie::factory()->create();

    $cart->toggleMovie($movie->id);
    $cart->clear();

    expect($cart->isEmpty())->toBeTrue();
});

it('returns empty arrays for movies and episodes by default', function () {
    $cart = new CartService;

    expect($cart->movies())->toBe([])
        ->and($cart->episodes())->toBe([]);
});

it('can mix movies and episodes in cart', function () {
    $cart = new CartService;
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();
    $episode = Episode::factory()->create();

    $cart->toggleMovie($movie1->id);
    $cart->toggleMovie($movie2->id);
    $cart->syncShowEpisodes($episode->show_id, [$episode->code]);

    expect($cart->count())->toBe(3)
        ->and($cart->has($movie1->id))->toBeTrue()
        ->and($cart->has($movie2->id))->toBeTrue()
        ->and($cart->has($episode))->toBeTrue();
});

it('can add a special episode to cart via sync', function () {
    $cart = new CartService;
    $episode = Episode::factory()->special()->create([
        'season' => 24,
        'number' => 1,
    ]);

    $cart->syncShowEpisodes($episode->show_id, ['S24S01']);

    expect($cart->count())->toBe(1)
        ->and($cart->has($episode))->toBeTrue()
        ->and($cart->episodes()[0]['code'])->toBe('s24s01');
});

it('can sync special episode via code', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $cart->syncShowEpisodes($show->id, ['S24S01']);

    expect($cart->count())->toBe(1)
        ->and($cart->episodes()[0]['code'])->toBe('s24s01');
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

    $cart->syncShowEpisodes($show->id, ['S24E01', 'S24S01']);

    expect($cart->count())->toBe(2)
        ->and($cart->has($regularEpisode))->toBeTrue()
        ->and($cart->has($specialEpisode))->toBeTrue();

    $codes = array_column($cart->episodes(), 'code');
    expect($codes)->toContain('s24e01')
        ->and($codes)->toContain('s24s01');
});

it('loads special episodes correctly', function () {
    $cart = new CartService;
    $episode = Episode::factory()->special()->create([
        'season' => 24,
        'number' => 1,
    ]);

    $cart->syncShowEpisodes($episode->show_id, ['S24S01']);
    $loaded = $cart->loadItems();

    expect($loaded)->toHaveCount(1)
        ->and($loaded->first()->id)->toBe($episode->id)
        ->and($loaded->first()->isSpecial())->toBeTrue();
});

it('can sync episodes for a show', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $cart->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);

    expect($cart->episodes())->toHaveCount(2);

    $codes = array_column($cart->episodes(), 'code');
    expect($codes)->toContain('s01e01')
        ->and($codes)->toContain('s01e02');
});

it('syncing episodes replaces existing episodes for the show', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    // Add initial episodes
    $cart->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);
    expect($cart->count())->toBe(2);

    // Sync with different episodes (should replace)
    $cart->syncShowEpisodes($show->id, ['S01E03', 'S01E04']);

    expect($cart->episodes())->toHaveCount(2);

    $codes = array_column($cart->episodes(), 'code');
    expect($codes)->toContain('s01e03')
        ->and($codes)->toContain('s01e04')
        ->and($codes)->not->toContain('s01e01')
        ->and($codes)->not->toContain('s01e02');
});

it('syncing episodes does not affect other shows', function () {
    $cart = new CartService;
    $show1 = \App\Models\Show::factory()->create();
    $show2 = \App\Models\Show::factory()->create();

    $cart->syncShowEpisodes($show1->id, ['S01E01', 'S01E02']);
    $cart->syncShowEpisodes($show2->id, ['S02E01']);

    // Now sync show1 with new episodes
    $cart->syncShowEpisodes($show1->id, ['S01E05']);

    $episodes = $cart->episodes();
    expect($episodes)->toHaveCount(2);

    // Show1 should have new episode only
    $show1Episodes = array_filter($episodes, fn ($ep) => $ep['show_id'] === $show1->id);
    expect(array_values(array_column($show1Episodes, 'code')))->toBe(['s01e05']);

    // Show2 should be unchanged
    $show2Episodes = array_filter($episodes, fn ($ep) => $ep['show_id'] === $show2->id);
    expect(array_values(array_column($show2Episodes, 'code')))->toBe(['s02e01']);
});

it('can sync with empty array to remove all episodes for a show', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $cart->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);
    expect($cart->count())->toBe(2);

    $cart->syncShowEpisodes($show->id, []);
    expect($cart->count())->toBe(0);
});

it('can sync special episodes', function () {
    $cart = new CartService;
    $show = \App\Models\Show::factory()->create();

    $cart->syncShowEpisodes($show->id, ['S01E01', 'S01S01']); // regular + special

    expect($cart->episodes())->toHaveCount(2);

    $codes = array_column($cart->episodes(), 'code');
    expect($codes)->toContain('s01e01')
        ->and($codes)->toContain('s01s01');
});
