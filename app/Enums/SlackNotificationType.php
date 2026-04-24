<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\MediaAvailableNotification;
use App\Notifications\PlexLibraryNotification;
use App\Notifications\RequestItemsNotification;
use App\Notifications\RequestProcessedNotification;
use App\Notifications\SubscriptionMediaNotification;
use Filament\Support\Contracts\HasLabel;

enum SlackNotificationType: string implements HasLabel
{
    case RequestItems = 'request_items';
    case RequestProcessed = 'request_processed';
    case MediaAvailable = 'media_available';
    case SubscriptionMedia = 'subscription_media';
    case PlexLibrary = 'plex_library';

    public function getLabel(): string
    {
        return match ($this) {
            self::RequestItems => 'New Request',
            self::RequestProcessed => 'Request Processed',
            self::MediaAvailable => 'Available',
            self::SubscriptionMedia => 'New Release',
            self::PlexLibrary => 'Added to Library',
        };
    }

    public function channel(): ?string
    {
        return match ($this) {
            self::PlexLibrary => config('services.slack.notifications.library_channel')
                ?: config('services.slack.notifications.channel'),
            default => config('services.slack.notifications.channel'),
        };
    }

    /**
     * @param  class-string  $notificationClass
     */
    public static function tryFromNotification(string $notificationClass): ?self
    {
        return match ($notificationClass) {
            RequestItemsNotification::class => self::RequestItems,
            RequestProcessedNotification::class => self::RequestProcessed,
            MediaAvailableNotification::class => self::MediaAvailable,
            SubscriptionMediaNotification::class => self::SubscriptionMedia,
            PlexLibraryNotification::class => self::PlexLibrary,
            default => null,
        };
    }
}
