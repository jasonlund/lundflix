<?php

namespace App\Listeners;

use App\Events\RequestFulfilled;
use App\Models\Episode;
use App\Notifications\RequestProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendRequestFulfilledNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RequestFulfilled $event): void
    {
        if (! config('services.slack.enabled')) {
            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
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
