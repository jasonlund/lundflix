<?php

use App\Events\RequestSubmitted;
use App\Models\Movie;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    session()->flush();
});

it('dispatches RequestSubmitted event on successful submit', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit')
        ->assertRedirect(route('home'));

    Event::assertDispatched(RequestSubmitted::class, function ($event) {
        return $event->request->exists;
    });
});

it('does not dispatch RequestSubmitted event when cart is empty', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->call('submit');

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('does not dispatch RequestSubmitted event when validation fails', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    app(CartService::class)->toggleMovie($movie->id);

    Livewire::actingAs($user)
        ->test('cart.checkout')
        ->set('notes', str_repeat('a', 1001))
        ->call('submit');

    Event::assertNotDispatched(RequestSubmitted::class);
});
