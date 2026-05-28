<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\MediaAvailableNotification;
use App\Notifications\MultiSeasonPackReviewNotification;
use App\Notifications\PlexLibraryNotification;
use App\Notifications\RequestItemsNotification;
use App\Notifications\RequestProcessedNotification;
use App\Notifications\SubscriptionMediaNotification;
use App\Notifications\TorrentIgnoredNotification;
use App\Notifications\TorrentNotFoundNotification;
use App\Notifications\TorrentOversizeNotification;
use App\Notifications\TorrentRejectedNotification;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SlackNotificationType: string implements HasColor, HasLabel
{
    case RequestItems = 'request_items';
    case RequestProcessed = 'request_processed';
    case MediaAvailable = 'media_available';
    case SubscriptionMedia = 'subscription_media';
    case PlexLibrary = 'plex_library';
    case TorrentRejected = 'torrent_rejected';
    case TorrentIgnored = 'torrent_ignored';
    case TorrentNotFound = 'torrent_not_found';
    case TorrentOversize = 'torrent_oversize';
    case MultiSeasonPackReview = 'multi_season_pack_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::RequestItems => 'New Request',
            self::RequestProcessed => 'Request Processed',
            self::MediaAvailable => 'Available',
            self::SubscriptionMedia => 'New Release',
            self::PlexLibrary => 'Added to Library',
            self::TorrentRejected => 'Torrent Rejected',
            self::TorrentIgnored => 'Torrent Ignored',
            self::TorrentNotFound => 'Torrent Not Found',
            self::TorrentOversize => 'Torrent Oversize',
            self::MultiSeasonPackReview => 'Multi-Season Pack Review',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RequestItems => 'info',
            self::RequestProcessed => 'success',
            self::MediaAvailable => 'success',
            self::SubscriptionMedia => 'warning',
            self::PlexLibrary => 'gray',
            self::TorrentRejected => 'danger',
            self::TorrentIgnored => 'warning',
            self::TorrentNotFound => 'warning',
            self::TorrentOversize => 'warning',
            self::MultiSeasonPackReview => 'info',
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
            TorrentRejectedNotification::class => self::TorrentRejected,
            TorrentIgnoredNotification::class => self::TorrentIgnored,
            TorrentNotFoundNotification::class => self::TorrentNotFound,
            TorrentOversizeNotification::class => self::TorrentOversize,
            MultiSeasonPackReviewNotification::class => self::MultiSeasonPackReview,
            default => null,
        };
    }
}
