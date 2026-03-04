<?php

use App\Actions\Request\MarkRequestItems;
use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;

it('computes status as pending when all items are pending', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->status)->toBe(RequestStatus::Pending);
});

it('computes status as partially fulfilled when some items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, [$request->items->first()->id], RequestItemStatus::Fulfilled);

    expect($request->status)->toBe(RequestStatus::PartiallyFulfilled);
});

it('computes status as fulfilled when all items are fulfilled', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Fulfilled);

    expect($request->status)->toBe(RequestStatus::Fulfilled);
});

it('computes status as pending when no items exist', function () {
    $request = Request::factory()->create();

    expect($request->status)->toBe(RequestStatus::Pending);
});

it('computes status as rejected when all items are rejected or not found', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Rejected);

    expect($request->status)->toBe(RequestStatus::Rejected);
});

it('computes status as rejected when items are a mix of rejected and not found', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(2)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, [$request->items[0]->id], RequestItemStatus::Rejected);
    $markItems->markAs($request, [$request->items[1]->id], RequestItemStatus::NotFound);

    expect($request->status)->toBe(RequestStatus::Rejected);
});

it('detects when request has rejected items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->has_rejected_items)->toBeFalse();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, [$request->items->first()->id], RequestItemStatus::Rejected);

    expect($request->has_rejected_items)->toBeTrue();
});

it('detects when request has not found items', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->has_not_found_items)->toBeFalse();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, [$request->items->first()->id], RequestItemStatus::NotFound);

    expect($request->has_not_found_items)->toBeTrue();
});

it('computes status based only on fulfilled items ignoring rejected and not found', function () {
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);

    // Mark one as rejected and one as not found
    $markItems->markAs($request, [$request->items[0]->id], RequestItemStatus::Rejected);
    $markItems->markAs($request, [$request->items[1]->id], RequestItemStatus::NotFound);

    // Status should still be pending since 0/3 are fulfilled and 1 item is unactioned
    expect($request->status)->toBe(RequestStatus::Pending);
    expect($request->has_rejected_items)->toBeTrue();
    expect($request->has_not_found_items)->toBeTrue();

    // Mark the last item as fulfilled
    $markItems->markAs($request, [$request->items[2]->id], RequestItemStatus::Fulfilled);

    // Now status should be partially fulfilled (1/3 fulfilled)
    expect($request->status)->toBe(RequestStatus::PartiallyFulfilled);
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

    $markItems = app(MarkRequestItems::class);
    $itemIds = $items->take(2)->pluck('id')->toArray();
    $updated = $markItems->markAs($request, $itemIds, RequestItemStatus::Fulfilled, $user->id);

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

    $markItems = app(MarkRequestItems::class);
    $updated = $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Fulfilled, $user->id);

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

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Fulfilled);

    expect($request->items->every(fn ($item) => $item->actioned_by === $user->id))->toBeTrue();
});

it('marks items as rejected with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $itemIds = $items->pluck('id')->toArray();
    $markItems->markAs($request, $itemIds, RequestItemStatus::Rejected, $user->id);

    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::Rejected &&
        $item->actioned_by === $user->id &&
        $item->actioned_at !== null))->toBeTrue();
});

it('marks items as not found with user tracking', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $itemIds = $items->pluck('id')->toArray();
    $markItems->markAs($request, $itemIds, RequestItemStatus::NotFound, $user->id);

    expect($request->items->every(fn ($item) => $item->status === RequestItemStatus::NotFound &&
        $item->actioned_by === $user->id &&
        $item->actioned_at !== null))->toBeTrue();
});

it('marks items as pending and clears tracking', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->fulfilled()->create();

    $markItems = app(MarkRequestItems::class);
    $itemIds = $items->pluck('id')->toArray();
    $markItems->markAs($request, $itemIds, RequestItemStatus::Pending);

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

    $markItems = app(MarkRequestItems::class);

    // Try to mark request2's items through request1 (should not work)
    $markItems->markAs($request1, $items2->pluck('id')->toArray(), RequestItemStatus::Fulfilled, $user->id);

    // Request2's items should still be pending
    expect($items2->fresh()->every(fn ($item) => $item->status === RequestItemStatus::Pending))->toBeTrue();
});

it('automatically refreshes request after marking items', function () {
    $user = User::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    expect($request->status)->toBe(RequestStatus::Pending);

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Fulfilled, $user->id);

    // Should be refreshed automatically, no manual refresh needed
    expect($request->status)->toBe(RequestStatus::Fulfilled);
});
