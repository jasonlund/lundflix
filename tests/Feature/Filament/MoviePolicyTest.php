<?php

use App\Models\Movie;
use App\Models\User;
use App\Policies\MoviePolicy;

beforeEach(function () {
    $this->policy = new MoviePolicy;
    $this->user = User::factory()->create();
    $this->movie = Movie::factory()->create();
});

it('allows viewing any movies', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
});

it('allows viewing a movie', function () {
    expect($this->policy->view($this->user, $this->movie))->toBeTrue();
});

it('denies creating movies', function () {
    expect($this->policy->create($this->user))->toBeFalse();
});

it('denies updating movies', function () {
    expect($this->policy->update($this->user, $this->movie))->toBeFalse();
});

it('denies deleting movies', function () {
    expect($this->policy->delete($this->user, $this->movie))->toBeFalse();
});

it('denies restoring movies', function () {
    expect($this->policy->restore($this->user, $this->movie))->toBeFalse();
});

it('denies force deleting movies', function () {
    expect($this->policy->forceDelete($this->user, $this->movie))->toBeFalse();
});
