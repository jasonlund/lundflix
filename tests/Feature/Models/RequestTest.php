<?php

use App\Models\Request;
use App\Models\RequestItem;

it('computes status as pending when all items are pending', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->status)->toBe('pending');
});

it('computes status as partially fulfilled when some items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request->items->first()->markFulfilled();
    $request->refresh();

    expect($request->status)->toBe('partially fulfilled');
});

it('computes status as fulfilled when all items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request->items->each->markFulfilled();
    $request->refresh();

    expect($request->status)->toBe('fulfilled');
});

it('computes status as pending when no items exist', function () {
    $request = Request::factory()->create();

    expect($request->status)->toBe('pending');
});

it('detects when request has rejected items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->has_rejected_items)->toBeFalse();

    $request->items->first()->markRejected();
    $request->refresh();

    expect($request->has_rejected_items)->toBeTrue();
});

it('detects when request has not found items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->has_not_found_items)->toBeFalse();

    $request->items->first()->markNotFound();
    $request->refresh();

    expect($request->has_not_found_items)->toBeTrue();
});

it('computes status based only on fulfilled items ignoring rejected and not found', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    // Mark one as rejected and one as not found
    $request->items[0]->markRejected();
    $request->items[1]->markNotFound();
    $request->refresh();

    // Status should still be pending since 0/3 are fulfilled
    expect($request->status)->toBe('pending');
    expect($request->has_rejected_items)->toBeTrue();
    expect($request->has_not_found_items)->toBeTrue();

    // Mark the last item as fulfilled
    $request->items[2]->markFulfilled();
    $request->refresh();

    // Now status should be partially fulfilled (1/3 fulfilled)
    expect($request->status)->toBe('partially fulfilled');
});

it('always eager loads items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request = Request::first();

    expect($request->relationLoaded('items'))->toBeTrue();
});
