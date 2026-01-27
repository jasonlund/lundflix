<?php

use App\Models\Show;
use App\Models\User;
use App\Policies\ShowPolicy;

beforeEach(function () {
    $this->policy = new ShowPolicy;
    $this->user = User::factory()->create();
    $this->show = Show::factory()->create();
});

it('allows viewing any shows', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
});

it('allows viewing a show', function () {
    expect($this->policy->view($this->user, $this->show))->toBeTrue();
});

it('denies creating shows', function () {
    expect($this->policy->create($this->user))->toBeFalse();
});

it('denies updating shows', function () {
    expect($this->policy->update($this->user, $this->show))->toBeFalse();
});

it('denies deleting shows', function () {
    expect($this->policy->delete($this->user, $this->show))->toBeFalse();
});

it('denies restoring shows', function () {
    expect($this->policy->restore($this->user, $this->show))->toBeFalse();
});

it('denies force deleting shows', function () {
    expect($this->policy->forceDelete($this->user, $this->show))->toBeFalse();
});
