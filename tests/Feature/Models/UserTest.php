<?php

use App\Enums\UserRole;
use App\Models\User;

it('identifies admin users', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->isAdmin())->toBeTrue();
});

it('identifies non-admin users', function () {
    $member = User::factory()->create();

    expect($member->isAdmin())->toBeFalse();
});

it('identifies server owners as non-admin', function () {
    $serverOwner = User::factory()->serverOwner()->create();

    expect($serverOwner->isAdmin())->toBeFalse();
});

it('casts role to UserRole enum', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBeInstanceOf(UserRole::class)
        ->and($user->role)->toBe(UserRole::Admin);
});

it('defaults to member role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Member);
});
