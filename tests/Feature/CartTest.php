<?php

use App\Enums\MovieStatus;
use App\Enums\RequestStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\User;
use Livewire\Livewire;

it('creates request from movies via submit', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['status' => MovieStatus::Released]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [])
        ->call('submit')
        ->assertDispatched('cart-submitted');

    expect(Request::count())->toBe(1)
        ->and(RequestItem::count())->toBe(1)
        ->and(Request::first()->user_id)->toBe($user->id)
        ->and(Request::first()->status)->toBe(RequestStatus::Pending);

    $item = RequestItem::first();
    expect($item->requestable_type)->toBe(Movie::class)
        ->and($item->requestable_id)->toBe($movie->id);
});

it('creates request from episodes via submit', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->for($show)->create([
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek()->format('Y-m-d'),
    ]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [], episodes: [
            ['show_id' => $show->id, 'code' => $episode->code],
        ])
        ->call('submit')
        ->assertDispatched('cart-submitted');

    expect(Request::count())->toBe(1)
        ->and(RequestItem::count())->toBe(1);

    $item = RequestItem::first();
    expect($item->requestable_type)->toBe(Episode::class)
        ->and($item->requestable_id)->toBe($episode->id);
});

it('creates request from mixed movies and episodes', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['status' => MovieStatus::Released]);
    $show = Show::factory()->create();
    $episode = Episode::factory()->for($show)->create([
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek()->format('Y-m-d'),
    ]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [
            ['show_id' => $show->id, 'code' => $episode->code],
        ])
        ->call('submit')
        ->assertDispatched('cart-submitted');

    expect(Request::count())->toBe(1)
        ->and(RequestItem::count())->toBe(2);
});

it('does not create request when no items in cart', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [], episodes: [])
        ->call('submit');

    expect(Request::count())->toBe(0);
});

it('does not create request when the cart payload only contains missing items', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [999999], episodes: [])
        ->assertSet('itemCount', 0)
        ->assertSee(__('lundbergh.empty.cart'))
        ->assertDontSee('Submit Request')
        ->call('submit')
        ->assertNotDispatched('cart-submitted');

    expect(Request::count())->toBe(0)
        ->and(RequestItem::count())->toBe(0);
});

it('rejects non-released movies from cart submission', function () {
    $user = User::factory()->create();
    $unreleased = Movie::factory()->create(['status' => MovieStatus::InProduction]);
    $canceled = Movie::factory()->create(['status' => MovieStatus::Canceled]);
    $released = Movie::factory()->create(['status' => MovieStatus::Released]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$unreleased->id, $canceled->id, $released->id], episodes: [])
        ->call('submit')
        ->assertDispatched('cart-submitted');

    expect(RequestItem::count())->toBe(1);

    $item = RequestItem::first();
    expect($item->requestable_id)->toBe($released->id);
});

it('rejects future episodes from cart submission', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $aired = Episode::factory()->for($show)->create([
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek()->format('Y-m-d'),
    ]);
    $future = Episode::factory()->for($show)->create([
        'season' => 1,
        'number' => 2,
        'airdate' => now()->addMonth()->format('Y-m-d'),
    ]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [], episodes: [
            ['show_id' => $show->id, 'code' => $aired->code],
            ['show_id' => $show->id, 'code' => $future->code],
        ])
        ->call('submit')
        ->assertDispatched('cart-submitted');

    expect(RequestItem::count())->toBe(1);

    $item = RequestItem::first();
    expect($item->requestable_id)->toBe($aired->id);
});

it('rejects episodes with no airdate from cart submission', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $noDate = Episode::factory()->for($show)->create([
        'season' => 1,
        'number' => 1,
        'airdate' => null,
    ]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [], episodes: [
            ['show_id' => $show->id, 'code' => $noDate->code],
        ])
        ->call('submit')
        ->assertNotDispatched('cart-submitted');

    expect(Request::count())->toBe(0);
});

it('displays grouped items when cart is opened', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'status' => MovieStatus::Released]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [])
        ->assertSet('groupedItems', fn ($items) => $items !== null && count($items['movies']) === 1)
        ->assertSet('movies', [$movie->id])
        ->assertSet('episodes', []);
});

it('counts each selected item in the cart header', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'status' => MovieStatus::Released]);
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(2)->sequence(
        ['season' => 1, 'number' => 1, 'airdate' => now()->subWeek()->format('Y-m-d')],
        ['season' => 1, 'number' => 2, 'airdate' => now()->subWeeks(2)->format('Y-m-d')],
    )->for($show)->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [
            ['show_id' => $show->id, 'code' => $episodes[0]->code],
            ['show_id' => $show->id, 'code' => $episodes[1]->code],
        ])
        ->assertSet('itemCount', 3)
        ->assertSee('Your Cart')
        ->assertSee('(3)');
});

it('displays empty cart message when groupedItems is null', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->assertSet('groupedItems', null)
        ->assertDontSee('Your Cart')
        ->assertDontSee('Submit Request');
});
