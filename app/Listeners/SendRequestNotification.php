<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Notifications\RequestItemsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendRequestNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RequestSubmitted $event): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Slack notification skipped: Slack is not enabled', [
                'request_id' => $event->request->id,
            ]);

            return;
        }

        $channel = SlackNotificationType::RequestItems->channel();

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
            Log::error('Slack notification skipped: request has no items', [
                'request_id' => $request->id,
            ]);

            return;
        }

        $notification = new RequestItemsNotification($request);

        Notification::route('slack', $channel)
            ->notify($notification);
    }
}
