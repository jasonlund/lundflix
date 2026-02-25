<?php

use App\Enums\RequestItemStatus;
use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
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

it('displays status badges for computed statuses', function () {
    $pending = Request::factory()->create();
    RequestItem::factory()->for($pending)->count(2)->create();

    $fulfilled = Request::factory()->create();
    RequestItem::factory()->for($fulfilled)->count(2)->create([
        'status' => RequestItemStatus::Fulfilled,
    ]);

    $partial = Request::factory()->create();
    RequestItem::factory()->for($partial)->create([
        'status' => RequestItemStatus::Fulfilled,
    ]);
    RequestItem::factory()->for($partial)->create();

    Livewire::test(ListRequests::class)
        ->assertCanSeeTableRecords([$pending, $fulfilled, $partial]);
});

it('does not show create button due to policy', function () {
    Livewire::test(ListRequests::class)
        ->assertDontSee('New request');
});
