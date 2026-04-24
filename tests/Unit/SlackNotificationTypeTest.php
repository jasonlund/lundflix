<?php

use App\Enums\SlackNotificationType;
use App\Notifications\MediaAvailableNotification;
use App\Notifications\PlexLibraryNotification;
use App\Notifications\RequestItemsNotification;
use App\Notifications\RequestProcessedNotification;
use App\Notifications\SubscriptionMediaNotification;

it('maps notification classes to enum cases', function (string $class, SlackNotificationType $expected) {
    expect(SlackNotificationType::tryFromNotification($class))->toBe($expected);
})->with([
    [RequestItemsNotification::class, SlackNotificationType::RequestItems],
    [RequestProcessedNotification::class, SlackNotificationType::RequestProcessed],
    [MediaAvailableNotification::class, SlackNotificationType::MediaAvailable],
    [SubscriptionMediaNotification::class, SlackNotificationType::SubscriptionMedia],
    [PlexLibraryNotification::class, SlackNotificationType::PlexLibrary],
]);

it('returns null for unknown notification classes', function () {
    expect(SlackNotificationType::tryFromNotification('App\Notifications\UnknownNotification'))->toBeNull();
});

it('has labels for all cases', function (SlackNotificationType $type) {
    expect($type->getLabel())->toBeString()->not->toBeEmpty();
})->with(SlackNotificationType::cases());
