<?php

namespace App\Listeners;

use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Notifications\RequestItemsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendRequestNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RequestSubmitted $event): void
    {
        if (! config('services.slack.enabled')) {
            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            return;
        }

        $request = $event->request->load(['items.requestable' => function ($morphTo) {
            $morphTo->morphWith([
                Episode::class => ['show'],
            ]);
        }]);

        if ($request->items->isEmpty()) {
            return;
        }

        Notification::route('slack', $channel)
            ->notify(new RequestItemsNotification($request));
    }
}
