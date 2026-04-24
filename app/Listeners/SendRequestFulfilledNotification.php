<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Events\RequestFulfilled;
use App\Models\Episode;
use App\Notifications\RequestProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendRequestFulfilledNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RequestFulfilled $event): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Slack notification skipped: Slack is not enabled', [
                'request_id' => $event->request->id,
            ]);

            return;
        }

        $channel = SlackNotificationType::RequestProcessed->channel();

        if (! $channel) {
            Log::warning('Slack notification skipped: channel not configured', [
                'request_id' => $event->request->id,
            ]);

            return;
        }

        $request = $event->request->load(['items.requestable' => function ($morphTo): void {
            $morphTo->morphWith([
                Episode::class => ['show'],
            ]);
        }]);

        if ($request->items->isEmpty()) {
            return;
        }

        Notification::route('slack', $channel)
            ->notify(new RequestProcessedNotification($request));
    }
}
