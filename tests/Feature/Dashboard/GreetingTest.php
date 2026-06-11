<?php

use App\Models\Request;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the new-user greeting when the user has no requests and no subscriptions', function () {
    Livewire::test('dashboard.greeting')
        ->assertSuccessful()
        ->assertSee("I'm… I'm Lundbergh", false);
});

it('does not show the new-user greeting when the user has a subscription', function () {
    Subscription::factory()->for($this->user)->create();

    Livewire::test('dashboard.greeting')
        ->assertSuccessful()
        ->assertDontSee("I'm… I'm Lundbergh", false);
});

it('does not show the new-user greeting when the user has a request', function () {
    Request::factory()->for($this->user)->create();

    Livewire::test('dashboard.greeting')
        ->assertSuccessful()
        ->assertDontSee("I'm… I'm Lundbergh", false);
});

it('hides the request and subscription tables when the user has neither', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSeeLivewire('dashboard.requests')
        ->assertDontSeeLivewire('dashboard.subscriptions');
});

it('shows both tables when the user has a request', function () {
    Request::factory()->for($this->user)->create();

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeLivewire('dashboard.requests')
        ->assertSeeLivewire('dashboard.subscriptions');
});

it('shows both tables when the user has a subscription', function () {
    Subscription::factory()->for($this->user)->create();

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeLivewire('dashboard.requests')
        ->assertSeeLivewire('dashboard.subscriptions');
});
