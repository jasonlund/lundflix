<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Events\MediaFoundInLibrary;
use App\Notifications\MediaInLibraryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendMediaFoundInLibraryNotification implements ShouldQueue
{
    use Queueable;

    public function handle(MediaFoundInLibrary $event): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Slack notification skipped: Slack is not enabled');

            return;
        }

        $channel = SlackNotificationType::MediaInLibrary->channel();

        if (! $channel) {
            Log::warning('Slack notification skipped: channel not configured');

            return;
        }

        Notification::route('slack', $channel)
            ->notify(new MediaInLibraryNotification($event->media, $event->episodes));
    }
}
