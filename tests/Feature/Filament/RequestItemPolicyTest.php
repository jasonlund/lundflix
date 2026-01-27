<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use App\Policies\RequestItemPolicy;

beforeEach(function () {
    $this->policy = new RequestItemPolicy;
    $this->user = User::factory()->create();
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

it('denies updating request items', function () {
    expect($this->policy->update($this->user, $this->requestItem))->toBeFalse();
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
