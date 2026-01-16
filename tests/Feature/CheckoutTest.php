<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

beforeEach(function () {
    session()->flush();
});

it('requires authentication', function () {
    $this->get(route('cart.checkout'))
        ->assertRedirect(route('login'));
});

it('shows empty cart message when no items', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cart.checkout'))
        ->assertSeeLivewire('cart.checkout')
        ->assertSee('Your cart is empty');
});

it('displays cart items', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie']);
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->assertSee('Test Movie');
});

it('can remove item from checkout', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeItem', 'movie', $movie->id)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->isEmpty())->toBeTrue();
});

it('creates request on submit', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', 'Please add soon!')
        ->call('submit')
        ->assertDispatched('cart-updated')
        ->assertRedirect(route('home'));

    expect(Request::count())->toBe(1)
        ->and(RequestItem::count())->toBe(1)
        ->and(Request::first()->notes)->toBe('Please add soon!')
        ->and(app(CartService::class)->isEmpty())->toBeTrue();
});

it('creates request without notes', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit')
        ->assertRedirect(route('home'));

    expect(Request::count())->toBe(1)
        ->and(Request::first()->notes)->toBeNull();
});

it('validates notes max length', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1001))
        ->call('submit')
        ->assertHasErrors(['notes' => 'max']);
});

it('prevents submit with empty cart', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit')
        ->assertHasErrors(['cart']);
});

it('associates request with authenticated user', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    expect(Request::first()->user_id)->toBe($user->id);
});

it('sets request status to pending', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    expect(Request::first()->status)->toBe('pending');
});

it('creates request item with correct polymorphic type', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    $item = RequestItem::first();
    expect($item->requestable_type)->toBe(Movie::class)
        ->and($item->requestable_id)->toBe($movie->id);
});

it('ignores invalid item types in removeItem', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeItem', 'invalid', $movie->id);

    expect(app(CartService::class)->has($movie))->toBeTrue();
});

it('validates notes max length on blur', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1001))
        ->assertHasErrors(['notes' => 'max']);
});

it('allows notes at max length', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->add($movie);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1000))
        ->assertHasNoErrors('notes');
});
