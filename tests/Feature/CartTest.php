<?php

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
    $movie = Movie::factory()->create();

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
    $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

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
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

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

it('displays grouped items when cart is opened', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie']);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [])
        ->assertSet('groupedItems', fn ($items) => $items !== null && count($items['movies']) === 1)
        ->assertSet('movies', [$movie->id])
        ->assertSet('episodes', []);
});

it('displays empty cart message when groupedItems is null', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->assertSet('groupedItems', null)
        ->assertDontSee('Your Cart')
        ->assertDontSee('Submit Request');
});
