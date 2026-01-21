<?php

use App\Models\Episode;
use App\Models\Show;
use App\Services\CartService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    session()->flush();
});

it('displays checkbox for each episode', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
    ]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->assertSeeHtml('data-flux-checkbox');
});

it('shows unchecked checkbox for episode not in cart', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
    ]);

    $component = Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes');

    expect($component->get('selectedEpisodes'))->toBeEmpty();
});

it('shows checked state for episode in cart', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
    ]);

    $cart = app(CartService::class);
    $cart->add($episode);

    $component = Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes');

    expect($component->get('selectedEpisodes'))->toContain($episode->code);
});

it('can add single episode via checkbox click', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);

    $episodeData = $episode->toArray();

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleEpisode', $episodeData)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($episode))->toBeTrue();
});

it('can remove single episode via checkbox click', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);

    $cart = app(CartService::class);
    $cart->add($episode);

    $episodeData = $episode->toArray();

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleEpisode', $episodeData)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($episode))->toBeFalse();
});

it('shows Request Season button when no episodes selected', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
    ]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->assertSee('Request Season')
        ->assertDontSee('Remove Season');
});

it('shows Request Season button when some episodes selected', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
    ]);

    $cart = app(CartService::class);
    $cart->add($episodes->first());

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->assertSee('Request Season')
        ->assertDontSee('Remove Season');
});

it('shows Remove Season button when all episodes selected', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episodes = Episode::factory()->count(2)->sequence(
        ['number' => 1],
        ['number' => 2],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
    ]);

    $cart = app(CartService::class);
    foreach ($episodes as $episode) {
        $cart->add($episode);
    }

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->assertSee('Remove Season')
        ->assertDontSee('Request Season');
});

it('adds all season episodes when clicking Request Season', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episodes = Episode::factory()->count(5)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
        ['number' => 4],
        ['number' => 5],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleSeason', 1)
        ->assertDispatched('cart-updated');

    $cart = app(CartService::class);
    foreach ($episodes as $episode) {
        expect($cart->has($episode))->toBeTrue();
    }
});

it('removes all season episodes when clicking Remove Season', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    $cart = app(CartService::class);
    foreach ($episodes as $episode) {
        $cart->add($episode);
    }

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleSeason', 1)
        ->assertDispatched('cart-updated');

    foreach ($episodes as $episode) {
        expect($cart->has($episode))->toBeFalse();
    }
});

it('only adds unselected episodes when some already selected', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    $cart = app(CartService::class);
    $cart->add($episodes->first());

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleSeason', 1)
        ->assertDispatched('cart-updated');

    // All should be in cart now
    foreach ($episodes as $episode) {
        expect($cart->has($episode))->toBeTrue();
    }

    // Count should be 3, not 4 (no duplicate)
    expect($cart->count())->toBe(3);
});

it('handles special episodes correctly', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $special = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'significant_special',
    ]);

    $episodeData = $special->toArray();

    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleEpisode', $episodeData)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($special))->toBeTrue();
});

it('updates selection state when cart-updated event received', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
    ]);

    $component = Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes');

    expect($component->get('selectedEpisodes'))->toBeEmpty();

    // Add episode to cart externally
    $cart = app(CartService::class);
    $cart->add($episode);

    // Dispatch cart-updated event to sync state
    $component->dispatch('cart-updated');

    expect($component->get('selectedEpisodes'))->toContain($episode->code);
});

it('handles multiple seasons correctly', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);

    $season1Episodes = Episode::factory()->count(2)->sequence(
        ['number' => 1],
        ['number' => 2],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    $season2Episodes = Episode::factory()->count(2)->sequence(
        ['number' => 1],
        ['number' => 2],
    )->create([
        'show_id' => $show->id,
        'season' => 2,
        'type' => 'regular',
    ]);

    // Add all season 1 episodes
    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->call('toggleSeason', 1)
        ->assertSee('Remove Season')  // Season 1 button should change
        ->assertSee('Request Season'); // Season 2 button should remain

    $cart = app(CartService::class);

    // Season 1 episodes should be in cart
    foreach ($season1Episodes as $episode) {
        expect($cart->has($episode))->toBeTrue();
    }

    // Season 2 episodes should not be in cart
    foreach ($season2Episodes as $episode) {
        expect($cart->has($episode))->toBeFalse();
    }
});

it('syncs cart when selectedEpisodes changes via wire:model', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);

    // Setting selectedEpisodes directly simulates wire:model.live behavior
    // This triggers updatedSelectedEpisodes() which needs the episodesByCode map
    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->set('selectedEpisodes', [$episode->code])
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($episode))->toBeTrue();
});

it('removes episode from cart when unchecked via wire:model', function () {
    $show = Show::factory()->create(['tvmaze_id' => 1]);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);

    $cart = app(CartService::class);
    $cart->add($episode);

    // Episode should start in cart
    expect($cart->has($episode))->toBeTrue();

    // Setting selectedEpisodes to empty simulates unchecking via wire:model
    Livewire::test('shows.episodes', ['show' => $show])
        ->call('loadEpisodes')
        ->set('selectedEpisodes', [])
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->has($episode))->toBeFalse();
});
