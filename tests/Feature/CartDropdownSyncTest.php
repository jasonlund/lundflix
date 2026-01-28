<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

beforeEach(function () {
    session()->flush();
});

describe('syncShowEpisodes', function () {
    it('adds episodes to cart from episode codes', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('syncShowEpisodes', $show->id, ['S01E01', 'S01E02'])
            ->assertDispatched('cart-updated');

        expect(app(CartService::class)->count())->toBe(2);
    });

    it('replaces existing episodes when syncing', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        // Pre-populate cart with episode 1
        $cart = app(CartService::class);
        $cart->syncShowEpisodes($show->id, ['S01E01']);
        expect($cart->count())->toBe(1);

        // Sync with only episode 2
        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('syncShowEpisodes', $show->id, ['S01E02']);

        $episodes = $cart->episodes();
        expect($episodes)->toHaveCount(1);
        expect($episodes[0]['code'])->toBe('s01e02');
    });

    it('removes all episodes when passed empty array', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        $cart = app(CartService::class);
        $cart->syncShowEpisodes($show->id, ['S01E01']);
        expect($cart->count())->toBe(1);

        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('syncShowEpisodes', $show->id, [])
            ->assertDispatched('cart-updated');

        expect($cart->count())->toBe(0);
    });

    it('does not affect other shows', function () {
        $user = User::factory()->create();
        $show1 = Show::factory()->create();
        $show2 = Show::factory()->create();

        $cart = app(CartService::class);
        $cart->syncShowEpisodes($show1->id, ['S01E01']);
        $cart->syncShowEpisodes($show2->id, ['S02E01']);

        // Sync show1 only
        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('syncShowEpisodes', $show1->id, ['S01E05']);

        $episodes = $cart->episodes();
        expect($episodes)->toHaveCount(2);

        $show2Episodes = array_filter($episodes, fn ($ep) => $ep['show_id'] === $show2->id);
        expect(array_values(array_column($show2Episodes, 'code')))->toBe(['s02e01']);
    });

    it('handles special episodes', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('syncShowEpisodes', $show->id, ['S00S01']); // special format

        $episodes = app(CartService::class)->episodes();
        expect($episodes[0]['code'])->toBe('s00s01');
    });
});

describe('toggleMovieInCart', function () {
    it('adds movie to cart', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();

        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('toggleMovieInCart', $movie->id)
            ->assertDispatched('cart-updated');

        expect(app(CartService::class)->has($movie->id))->toBeTrue();
    });

    it('removes movie when already in cart', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();

        $cart = app(CartService::class);
        $cart->toggleMovie($movie->id);
        expect($cart->count())->toBe(1);

        Livewire::actingAs($user)
            ->test('cart.dropdown')
            ->call('toggleMovieInCart', $movie->id)
            ->assertDispatched('cart-updated');

        expect($cart->has($movie->id))->toBeFalse();
        expect($cart->count())->toBe(0);
    });
});
