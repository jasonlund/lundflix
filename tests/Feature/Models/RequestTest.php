<?php

use App\Enums\RequestItemStatus;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;

it('computes status as pending when all items are pending', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->status)->toBe('pending');
});

it('computes status as partially fulfilled when some items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request->markItemsFulfilled([$request->items->first()->id]);

    expect($request->status)->toBe('partially fulfilled');
});

it('computes status as fulfilled when all items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request->markAllItemsFulfilled();

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

    $request->markItemsRejected([$request->items->first()->id]);

    expect($request->has_rejected_items)->toBeTrue();
});

it('detects when request has not found items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->has_not_found_items)->toBeFalse();

    $request->markItemsNotFound([$request->items->first()->id]);

    expect($request->has_not_found_items)->toBeTrue();
});

it('computes status based only on fulfilled items ignoring rejected and not found', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    // Mark one as rejected and one as not found
    $request->markItemsRejected([$request->items[0]->id]);
    $request->markItemsNotFound([$request->items[1]->id]);

    // Status should still be pending since 0/3 are fulfilled
    expect($request->status)->toBe('pending');
    expect($request->has_rejected_items)->toBeTrue();
    expect($request->has_not_found_items)->toBeTrue();

    // Mark the last item as fulfilled
    $request->markItemsFulfilled([$request->items[2]->id]);

    // Now status should be partially fulfilled (1/3 fulfilled)
    expect($request->status)->toBe('partially fulfilled');
});

it('always eager loads items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $request = Request::first();

    expect($request->relationLoaded('items'))->toBeTrue();
});

it('marks specific items as fulfilled with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $itemIds = $items->take(2)->pluck('id')->toArray();
    $updated = $request->markItemsFulfilled($itemIds, $user->id);

    expect($updated)->toBe(2);

    $items->fresh()->take(2)->each(function ($item) use ($user) {
        expect($item->status)->toBe(RequestItemStatus::Fulfilled);
        expect($item->actioned_by)->toBe($user->id);
        expect($item->actioned_at)->not->toBeNull();
    });

    // Third item should still be pending
    expect($items->fresh()->last()->status)->toBe(RequestItemStatus::Pending);
});

it('marks all items as fulfilled with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $updated = $request->markAllItemsFulfilled($user->id);

    expect($updated)->toBe(3);
    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::Fulfilled &&
        $item->actioned_by === $user->id &&
        $item->actioned_at !== null))->toBeTrue();
});

it('marks items as fulfilled using authenticated user when no user provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $request->markAllItemsFulfilled();

    expect($request->items->every(fn ($item) => $item->actioned_by === $user->id))->toBeTrue();
});

it('marks items as rejected with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $itemIds = $items->pluck('id')->toArray();
    $request->markItemsRejected($itemIds, $user->id);

    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::Rejected &&
        $item->actioned_by === $user->id &&
        $item->actioned_at !== null))->toBeTrue();
});

it('marks items as not found with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $itemIds = $items->pluck('id')->toArray();
    $request->markItemsNotFound($itemIds, $user->id);

    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::NotFound &&
        $item->actioned_by === $user->id &&
        $item->actioned_at !== null))->toBeTrue();
});

it('marks items as pending and clears tracking', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->fulfilled()->create();

    $itemIds = $items->pluck('id')->toArray();
    $request->markItemsPending($itemIds);

    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::Pending &&
        $item->actioned_by === null &&
        $item->actioned_at === null))->toBeTrue();
});

it('only updates items belonging to the request', function () {
    $user = User::factory()->create();
    $request1 = Request::factory()->create();
    $request2 = Request::factory()->create();

    $items1 = RequestItem::factory()->for($request1)->count(2)->create();
    $items2 = RequestItem::factory()->for($request2)->count(2)->create();

    // Try to mark request2's items through request1 (should not work)
    $request1->markItemsFulfilled($items2->pluck('id')->toArray(), $user->id);

    // Request2's items should still be pending
    expect($items2->fresh()->every(fn ($item) => $item->status === RequestItemStatus::Pending))->toBeTrue();
});

it('automatically refreshes request after marking items', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->status)->toBe('pending');

    $request->markAllItemsFulfilled($user->id);

    // Should be refreshed automatically, no manual refresh needed
    expect($request->status)->toBe('fulfilled');
});
