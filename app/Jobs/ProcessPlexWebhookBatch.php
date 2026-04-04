<?php

namespace App\Jobs;

use App\Models\PlexWebhookEvent;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class ProcessPlexWebhookBatch implements ShouldBeUnique, ShouldQueue
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
            self::dispatch($this->serverUuid)
                ->delay(now()->addSeconds($debounceSeconds));

            return;
        }

        PlexWebhookEvent::query()
            ->whereIn('id', $events->pluck('id'))
            ->update(['processed_at' => now()]);

        if (! config('services.slack.enabled')) {
            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            return;
        }

        Notification::route('slack', $channel)
            ->notify(new PlexLibraryNotification($events));
    }
}
