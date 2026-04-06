<?php

namespace App\Jobs;

use App\Notifications\PlexLibraryNotification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
        $cacheKey = "plex-webhook:{$this->serverUuid}";

        $batch = Cache::pull($cacheKey);

        if (! $batch || empty($batch['items'])) {
            return;
        }

        $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds', 30);
        $lastReceivedAt = $batch['last_received_at'] ?? 0;

        if (now()->timestamp - $lastReceivedAt < $debounceSeconds) {
            Cache::put($cacheKey, $batch, now()->addHours(1));
            $this->release($debounceSeconds);

            return;
        }

        if (config('services.slack.enabled')) {
            $channel = config('services.slack.notifications.channel');

            if ($channel) {
                Notification::route('slack', $channel)
                    ->notify(new PlexLibraryNotification(
                        serverName: $batch['server_name'],
                        items: collect($batch['items']),
                    ));
            }
        }
    }
}
