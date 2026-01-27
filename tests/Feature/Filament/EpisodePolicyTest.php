<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Policies\EpisodePolicy;

beforeEach(function () {
    $this->policy = new EpisodePolicy;
    $this->user = User::factory()->create();
    $this->show = Show::factory()->create();
    $this->episode = Episode::factory()->create(['show_id' => $this->show->id]);
});

it('allows viewing any episodes', function () {
    expect($this->policy->viewAny($this->user))->toBeTrue();
});

it('allows viewing an episode', function () {
    expect($this->policy->view($this->user, $this->episode))->toBeTrue();
});

it('denies creating episodes', function () {
    expect($this->policy->create($this->user))->toBeFalse();
});

it('denies updating episodes', function () {
    expect($this->policy->update($this->user, $this->episode))->toBeFalse();
});

it('denies deleting episodes', function () {
    expect($this->policy->delete($this->user, $this->episode))->toBeFalse();
});

it('denies restoring episodes', function () {
    expect($this->policy->restore($this->user, $this->episode))->toBeFalse();
});

it('denies force deleting episodes', function () {
    expect($this->policy->forceDelete($this->user, $this->episode))->toBeFalse();
});
