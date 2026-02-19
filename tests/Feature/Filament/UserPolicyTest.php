<?php

use App\Models\User;
use App\Policies\UserPolicy;

beforeEach(function () {
    $this->policy = new UserPolicy;
    $this->admin = User::factory()->admin()->create();
    $this->serverOwner = User::factory()->serverOwner()->create();
    $this->member = User::factory()->create();
    $this->targetUser = User::factory()->create();
});

it('allows admin to view any users', function () {
    expect($this->policy->viewAny($this->admin))->toBeTrue();
});

it('denies server owner from viewing any users', function () {
    expect($this->policy->viewAny($this->serverOwner))->toBeFalse();
});

it('denies member from viewing any users', function () {
    expect($this->policy->viewAny($this->member))->toBeFalse();
});

it('allows admin to view a user', function () {
    expect($this->policy->view($this->admin, $this->targetUser))->toBeTrue();
});

it('denies server owner from viewing a user', function () {
    expect($this->policy->view($this->serverOwner, $this->targetUser))->toBeFalse();
});

it('denies member from viewing a user', function () {
    expect($this->policy->view($this->member, $this->targetUser))->toBeFalse();
});

it('denies creating users', function () {
    expect($this->policy->create($this->admin))->toBeFalse();
});

it('allows admin to update a user', function () {
    expect($this->policy->update($this->admin, $this->targetUser))->toBeTrue();
});

it('denies server owner from updating a user', function () {
    expect($this->policy->update($this->serverOwner, $this->targetUser))->toBeFalse();
});

it('denies member from updating a user', function () {
    expect($this->policy->update($this->member, $this->targetUser))->toBeFalse();
});

it('denies deleting users', function () {
    expect($this->policy->delete($this->admin, $this->targetUser))->toBeFalse();
});

it('denies restoring users', function () {
    expect($this->policy->restore($this->admin, $this->targetUser))->toBeFalse();
});

it('denies force deleting users', function () {
    expect($this->policy->forceDelete($this->admin, $this->targetUser))->toBeFalse();
});
