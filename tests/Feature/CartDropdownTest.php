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

it('shows empty cart message when no items', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 0)
        ->assertSeeHtml('<span class="sr-only sm:not-sr-only">Cart</span>')
        ->assertSee(__('lundbergh.empty.cart_dropdown'));
});

it('shows cart count when items present', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 1);
});

it('updates count when cart-updated event received', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSet('itemCount', 0);

    app(CartService::class)->toggleMovie($movie->id);

    $component->dispatch('cart-updated')
        ->assertSet('itemCount', 1);
});

it('displays movie title in cart dropdown', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie Title']);
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee('Test Movie Title');
});

it('renders inline count instead of badge when items present', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertDontSeeHtml('data-flux-badge')
        ->assertSeeHtml('tabular-nums');
});

it('does not render inline count when cart is empty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertDontSeeHtml('data-flux-badge');
});

it('shows cart heading with count when items in cart', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee('Your Cart (1)');
});

it('shows submit request button when cart has items', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee('Submit Request');
});

it('does not show submit request button when cart is empty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertDontSee('Submit Request');
});

it('shows checkout hint when cart has items', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertSee(__('lundbergh.cart.checkout_hint'));
});

it('does not show checkout hint when cart is empty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->assertDontSee(__('lundbergh.cart.checkout_hint'));
});

it('creates request on submit', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->call('submit')
        ->assertDispatched('cart-updated');

    expect(Request::count())->toBe(1)
        ->and(RequestItem::count())->toBe(1)
        ->and(Request::first()->notes)->toBeNull()
        ->and(app(CartService::class)->isEmpty())->toBeTrue();
});

it('does not create request when cart is empty', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->call('submit');

    expect(Request::count())->toBe(0);
});

it('associates request with authenticated user', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->call('submit');

    expect(Request::first()->user_id)->toBe($user->id);
});

it('sets request status to pending', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->call('submit');

    expect(Request::first()->status)->toBe('pending');
});

it('creates request item with correct polymorphic type', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.dropdown')
        ->call('submit');

    $item = RequestItem::first();
    expect($item->requestable_type)->toBe(Movie::class)
        ->and($item->requestable_id)->toBe($movie->id);
});
