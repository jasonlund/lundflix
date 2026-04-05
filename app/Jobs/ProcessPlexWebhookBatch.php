<?php

namespace App\Jobs;

use App\Models\PlexWebhookEvent;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class ProcessPlexWebhookBatch implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public string $serverUuid) {}

    public function uniqueId(): string
    {
        return $this->serverUuid;
    }

    public function uniqueFor(): int
    {
        return (int) config('services.plex.webhook_debounce_seconds', 30);
    }

    public function handle(): void
    {
        $events = PlexWebhookEvent::query()
            ->unprocessed()
            ->forServer($this->serverUuid)
            ->orderBy('created_at')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds', 30);
        $latestEvent = $events->last();

        if ($latestEvent->created_at->diffInSeconds(now()) < $debounceSeconds) {
            $this->release($debounceSeconds);

            return;
        }

        if (config('services.slack.enabled')) {
            $channel = config('services.slack.notifications.channel');

            if ($channel) {
                Notification::route('slack', $channel)
                    ->notify(new PlexLibraryNotification($events));
            }
        }

        PlexWebhookEvent::query()
            ->whereIn('id', $events->pluck('id'))
            ->update(['processed_at' => now()]);
    }
}
