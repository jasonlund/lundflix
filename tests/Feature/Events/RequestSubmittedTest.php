<?php

use App\Enums\MovieStatus;
use App\Events\RequestSubmitted;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('dispatches RequestSubmitted event on successful submit', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create(['status' => MovieStatus::Released]);

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [$movie->id], episodes: [])
        ->call('submit');

    Event::assertDispatched(RequestSubmitted::class, function ($event) {
        return $event->request->exists;
    });
});

it('does not dispatch RequestSubmitted event when cart is empty', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('cart')
        ->dispatch('open-cart', movies: [], episodes: [])
        ->call('submit');

    Event::assertNotDispatched(RequestSubmitted::class);
});
