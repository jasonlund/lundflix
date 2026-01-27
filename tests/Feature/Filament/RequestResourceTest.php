<?php

use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Models\Request;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListRequests::class)->assertSuccessful();
});

it('can render the view page', function () {
    $request = Request::factory()->create();

    Livewire::test(ViewRequest::class, ['record' => $request->getRouteKey()])
        ->assertSuccessful();
});

it('displays requests in the table', function () {
    $requests = Request::factory()->count(3)->create();

    Livewire::test(ListRequests::class)
        ->assertCanSeeTableRecords($requests);
});

it('displays status badges with correct colors', function () {
    $pending = Request::factory()->create(['status' => 'pending']);
    $fulfilled = Request::factory()->fulfilled()->create();
    $rejected = Request::factory()->rejected()->create();

    Livewire::test(ListRequests::class)
        ->assertCanSeeTableRecords([$pending, $fulfilled, $rejected]);
});

it('can filter requests by status', function () {
    $pending = Request::factory()->create(['status' => 'pending']);
    $fulfilled = Request::factory()->fulfilled()->create();

    Livewire::test(ListRequests::class)
        ->filterTable('status', 'fulfilled')
        ->assertCanSeeTableRecords([$fulfilled])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('does not show create button due to policy', function () {
    Livewire::test(ListRequests::class)
        ->assertDontSee('New request');
});
