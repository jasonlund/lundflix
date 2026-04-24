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

it('returns the library channel for PlexLibrary when configured', function () {
    config([
        'services.slack.notifications.channel' => 'C-default',
        'services.slack.notifications.library_channel' => 'C-library',
    ]);

    expect(SlackNotificationType::PlexLibrary->channel())->toBe('C-library');
});

it('falls back to default channel for PlexLibrary when library channel is not set', function () {
    config([
        'services.slack.notifications.channel' => 'C-default',
        'services.slack.notifications.library_channel' => null,
    ]);

    expect(SlackNotificationType::PlexLibrary->channel())->toBe('C-default');
});

it('returns the default channel for non-library notification types', function (SlackNotificationType $type) {
    config([
        'services.slack.notifications.channel' => 'C-default',
        'services.slack.notifications.library_channel' => 'C-library',
    ]);

    expect($type->channel())->toBe('C-default');
})->with([
    SlackNotificationType::RequestItems,
    SlackNotificationType::RequestProcessed,
    SlackNotificationType::MediaAvailable,
    SlackNotificationType::SubscriptionMedia,
]);
