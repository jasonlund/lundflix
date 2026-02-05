<?php

use App\Enums\RequestItemStatus;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use App\Policies\RequestItemPolicy;

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->policy = new RequestItemPolicy;
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->nonAdmin = User::factory()->create(['plex_token' => 'non-admin-token']);
    $this->otherUser = User::factory()->create(['plex_token' => 'other-token']);
    $this->request = Request::factory()->create();
    $this->requestItem = RequestItem::factory()->create([
        'request_id' => $this->request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => Movie::factory()->create()->id,
    ]);
});

it('allows viewing any request items', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
});

it('allows viewing a request item', function () {
    expect($this->policy->view($this->user, $this->requestItem))->toBeTrue();
});

it('denies creating request items', function () {
    expect($this->policy->create($this->user))->toBeFalse();
});

it('denies updating items actioned by others', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->nonAdmin, $item))->toBeFalse();
});

it('denies deleting request items', function () {
    expect($this->policy->delete($this->user, $this->requestItem))->toBeFalse();
});

it('denies restoring request items', function () {
    expect($this->policy->restore($this->user, $this->requestItem))->toBeFalse();
});

it('denies force deleting request items', function () {
    expect($this->policy->forceDelete($this->user, $this->requestItem))->toBeFalse();
});

it('allows admin to update any request item status', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->admin, $item, RequestItemStatus::Rejected))
        ->toBeTrue();
});

it('allows admin to reset any item to pending', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->admin, $item, RequestItemStatus::Pending))
        ->toBeTrue();
});

it('allows non-admin to update pending items', function () {
    $item = RequestItem::factory()->pending()->create();

    expect($this->policy->update($this->nonAdmin, $item, RequestItemStatus::Fulfilled))
        ->toBeTrue();
});

it('allows non-admin to update items they actioned', function () {
    $item = RequestItem::factory()->fulfilled($this->nonAdmin->id)->create();

    expect($this->policy->update($this->nonAdmin, $item, RequestItemStatus::Rejected))
        ->toBeTrue();
});

it('denies non-admin from updating items actioned by others', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->nonAdmin, $item, RequestItemStatus::Rejected))
        ->toBeFalse();
});

it('denies non-admin from resetting items actioned by others to pending', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->nonAdmin, $item, RequestItemStatus::Pending))
        ->toBeFalse();
});

it('allows non-admin to reset items they actioned to pending', function () {
    $item = RequestItem::factory()->fulfilled($this->nonAdmin->id)->create();

    expect($this->policy->update($this->nonAdmin, $item, RequestItemStatus::Pending))
        ->toBeTrue();
});

it('allows changing pending items without specifying new status', function () {
    $item = RequestItem::factory()->pending()->create();

    expect($this->policy->update($this->nonAdmin, $item))
        ->toBeTrue();
});

it('allows user to change items they actioned without specifying new status', function () {
    $item = RequestItem::factory()->fulfilled($this->nonAdmin->id)->create();

    expect($this->policy->update($this->nonAdmin, $item))
        ->toBeTrue();
});

it('denies user from changing items others actioned without specifying new status', function () {
    $item = RequestItem::factory()->fulfilled($this->otherUser->id)->create();

    expect($this->policy->update($this->nonAdmin, $item))
        ->toBeFalse();
});
