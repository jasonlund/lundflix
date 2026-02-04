<?php

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use App\Models\User;

it('marks item as fulfilled with user tracking', function () {
    $user = User::factory()->create();
    $item = RequestItem::factory()->create();

    $item->markFulfilled($user->id);

    expect($item->status)->toBe(RequestItemStatus::Fulfilled);
    expect($item->actioned_by)->toBe($user->id);
    expect($item->actionedBy->id)->toBe($user->id);
    expect($item->actioned_at)->not->toBeNull();
});

it('marks item as fulfilled using authenticated user when no user provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = RequestItem::factory()->create();
    $item->markFulfilled();

    expect($item->status)->toBe(RequestItemStatus::Fulfilled);
    expect($item->actioned_by)->toBe($user->id);
    expect($item->actioned_at)->not->toBeNull();
});

it('marks item as rejected', function () {
    $item = RequestItem::factory()->fulfilled()->create();

    $item->markRejected();

    expect($item->status)->toBe(RequestItemStatus::Rejected);
    expect($item->actioned_by)->toBeNull();
    expect($item->actioned_at)->toBeNull();
});

it('marks item as not found', function () {
    $item = RequestItem::factory()->fulfilled()->create();

    $item->markNotFound();

    expect($item->status)->toBe(RequestItemStatus::NotFound);
    expect($item->actioned_by)->toBeNull();
    expect($item->actioned_at)->toBeNull();
});

it('marks item as pending', function () {
    $item = RequestItem::factory()->fulfilled()->create();

    $item->markPending();

    expect($item->status)->toBe(RequestItemStatus::Pending);
    expect($item->actioned_by)->toBeNull();
    expect($item->actioned_at)->toBeNull();
});

it('scopes fulfilled items', function () {
    RequestItem::factory()->count(2)->fulfilled()->create();
    RequestItem::factory()->count(3)->pending()->create();

    $fulfilledCount = RequestItem::fulfilled()->count();

    expect($fulfilledCount)->toBe(2);
});

it('scopes pending items', function () {
    RequestItem::factory()->count(2)->fulfilled()->create();
    RequestItem::factory()->count(3)->pending()->create();

    $pendingCount = RequestItem::pending()->count();

    expect($pendingCount)->toBe(3);
});

it('scopes rejected items', function () {
    RequestItem::factory()->count(2)->rejected()->create();
    RequestItem::factory()->count(3)->pending()->create();

    $rejectedCount = RequestItem::rejected()->count();

    expect($rejectedCount)->toBe(2);
});

it('scopes not found items', function () {
    RequestItem::factory()->count(2)->notFound()->create();
    RequestItem::factory()->count(3)->pending()->create();

    $notFoundCount = RequestItem::notFound()->count();

    expect($notFoundCount)->toBe(2);
});

it('creates pending item by default', function () {
    $item = RequestItem::factory()->create();

    expect($item->status)->toBe(RequestItemStatus::Pending);
    expect($item->actioned_by)->toBeNull();
    expect($item->actioned_at)->toBeNull();
});

it('creates fulfilled item with factory state', function () {
    $item = RequestItem::factory()->fulfilled()->create();

    expect($item->status)->toBe(RequestItemStatus::Fulfilled);
    expect($item->actioned_by)->not->toBeNull();
    expect($item->actioned_at)->not->toBeNull();
});

it('creates rejected item with factory state', function () {
    $item = RequestItem::factory()->rejected()->create();

    expect($item->status)->toBe(RequestItemStatus::Rejected);
});

it('creates not found item with factory state', function () {
    $item = RequestItem::factory()->notFound()->create();

    expect($item->status)->toBe(RequestItemStatus::NotFound);
});
