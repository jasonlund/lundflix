<?php

use App\Models\Request;
use App\Models\User;
use App\Policies\RequestPolicy;

beforeEach(function () {
    $this->policy = new RequestPolicy;
    $this->user = User::factory()->create();
    $this->request = Request::factory()->create();
});

it('allows viewing any requests', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
});

it('allows viewing a request', function () {
    expect($this->policy->view($this->user, $this->request))->toBeTrue();
});

it('denies creating requests', function () {
    expect($this->policy->create($this->user))->toBeFalse();
});

it('denies updating requests', function () {
    expect($this->policy->update($this->user, $this->request))->toBeFalse();
});

it('denies deleting requests', function () {
    expect($this->policy->delete($this->user, $this->request))->toBeFalse();
});

it('denies restoring requests', function () {
    expect($this->policy->restore($this->user, $this->request))->toBeFalse();
});

it('denies force deleting requests', function () {
    expect($this->policy->forceDelete($this->user, $this->request))->toBeFalse();
});
