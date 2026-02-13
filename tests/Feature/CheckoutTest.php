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
        ->assertSee(__('lundbergh.empty.cart_checkout'));
});

it('displays cart items', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie']);
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->assertSee('Test Movie');
});

it('can remove movie from checkout', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeMovie', $movie->id)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->isEmpty())->toBeTrue();
});

it('creates request on submit', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', 'Please add soon!')
        ->call('submit')
        ->assertDispatched('toast-show')
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
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit')
        ->assertDispatched('toast-show')
        ->assertDispatched('cart-updated')
        ->assertRedirect(route('home'));

    expect(Request::count())->toBe(1)
        ->and(Request::first()->notes)->toBeNull();
});

it('validates notes max length', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

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
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    expect(Request::first()->user_id)->toBe($user->id);
});

it('sets request status to pending', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    expect(Request::first()->status)->toBe('pending');
});

it('creates request item with correct polymorphic type', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    $item = RequestItem::first();
    expect($item->requestable_type)->toBe(Movie::class)
        ->and($item->requestable_id)->toBe($movie->id);
});

it('can remove episodes from checkout', function () {
    $user = User::factory()->create();
    $show = \App\Models\Show::factory()->create();
    $episodes = \App\Models\Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    $cart = app(CartService::class);
    $cart->syncShowEpisodes($show->id, $episodes->pluck('code')->all());

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeEpisodes', $episodes->pluck('id')->all())
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->isEmpty())->toBeTrue();
});

it('validates notes max length on blur', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1001))
        ->assertHasErrors(['notes' => 'max']);
});

it('allows notes at max length', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1000))
        ->assertHasNoErrors('notes');
});

it('ignores empty episode IDs array in removeEpisodes', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeEpisodes', [])
        ->assertNotDispatched('cart-updated');

    expect(app(CartService::class)->count())->toBe(1);
});

it('ignores non-existent episode IDs in removeEpisodes', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeEpisodes', [99999, 99998, 99997])
        ->assertNotDispatched('cart-updated');

    expect(app(CartService::class)->count())->toBe(1);
});

it('ignores episodes not in cart in removeEpisodes', function () {
    $user = User::factory()->create();
    $show = \App\Models\Show::factory()->create();
    $episode = \App\Models\Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
    ]);

    // Episode exists but is not in cart
    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('removeEpisodes', [$episode->id])
        ->assertNotDispatched('cart-updated');
});
