<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Events\MediaAvailable;
use App\Notifications\MediaAvailableNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendMediaAvailableNotification implements ShouldQueue
{
    use Queueable;

    public function handle(MediaAvailable $event): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Slack notification skipped: Slack is not enabled', [
                'request_id' => $event->request?->id,
            ]);

            return;
        }

        $channel = SlackNotificationType::MediaAvailable->channel();

        if (! $channel) {
            Log::warning('Slack notification skipped: channel not configured', [
                'request_id' => $event->request?->id,
            ]);

            return;
        }

        Notification::route('slack', $channel)
            ->notify(new MediaAvailableNotification($event->media, $event->episodes));
    }
}
