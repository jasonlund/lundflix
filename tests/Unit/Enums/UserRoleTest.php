<?php

use App\Enums\UserRole;
use Filament\Support\Icons\Heroicon;

it('has exactly three roles', function () {
    expect(UserRole::cases())->toHaveCount(3);
});

it('has the expected backing values', function () {
    expect(UserRole::Admin->value)->toBe('admin')
        ->and(UserRole::ServerOwner->value)->toBe('server_owner')
        ->and(UserRole::Member->value)->toBe('member');
});

it('returns a label for every role', function () {
    foreach (UserRole::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});

it('returns a color for every role', function () {
    foreach (UserRole::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBeEmpty();
    }
});

it('returns an icon for every role', function () {
    foreach (UserRole::cases() as $case) {
        expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
    }
});
