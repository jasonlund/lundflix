<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Events\SubscriptionTriggered;
use App\Notifications\SubscriptionMediaNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSubscriptionNotification implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionTriggered $event): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Slack notification skipped: Slack is not enabled', [
                'request_id' => $event->request?->id,
            ]);

            return;
        }

        $channel = SlackNotificationType::SubscriptionMedia->channel();

        if (! $channel) {
            Log::warning('Slack notification skipped: channel not configured', [
                'request_id' => $event->request?->id,
            ]);

            return;
        }

        $notification = new SubscriptionMediaNotification($event->media, $event->episodes);

        Notification::route('slack', $channel)
            ->notify($notification);
    }
}
