<?php

use App\Actions\Request\MarkRequestItems;
use App\Enums\RequestItemStatus;
use App\Events\RequestFulfilled;
use App\Models\Request;
use App\Models\RequestItem;
use Illuminate\Support\Facades\Event;

it('dispatches RequestFulfilled event when all items are marked fulfilled', function () {
    Event::fake([RequestFulfilled::class]);

    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Fulfilled);

    Event::assertDispatched(RequestFulfilled::class, function ($event) use ($request) {
        return $event->request->id === $request->id;
    });
});

it('does not dispatch RequestFulfilled event for partial fulfillment', function () {
    Event::fake([RequestFulfilled::class]);

    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, [$request->items->first()->id], RequestItemStatus::Fulfilled);

    Event::assertNotDispatched(RequestFulfilled::class);
});

it('does not dispatch RequestFulfilled event when marking items as rejected', function () {
    Event::fake([RequestFulfilled::class]);

    $request = Request::factory()->create();
    RequestItem::factory()->for($request)->count(3)->create();

    $markItems = app(MarkRequestItems::class);
    $markItems->markAs($request, $request->items->pluck('id')->toArray(), RequestItemStatus::Rejected);

    Event::assertNotDispatched(RequestFulfilled::class);
});
