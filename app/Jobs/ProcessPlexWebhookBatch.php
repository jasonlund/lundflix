<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Notifications\PlexLibraryNotification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        $result = Cache::lock("{$cacheKey}:lock", 10)->block(5, function () use ($cacheKey): ?array {
            $batch = Cache::get($cacheKey);

            if (! $batch || empty($batch['items'])) {
                return null;
            }

            $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds', 30);
            $lastReceivedAt = $batch['last_received_at'] ?? 0;

            if (now()->timestamp - $lastReceivedAt < $debounceSeconds) {
                return ['action' => 'release'];
            }

            Cache::forget($cacheKey);

            return ['action' => 'process', 'batch' => $batch];
        });

        if (! $result) {
            Log::debug('Plex webhook batch empty or expired', ['serverUuid' => $this->serverUuid]);

            return;
        }

        if ($result['action'] === 'release') {
            $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds', 30);
            $this->release($debounceSeconds);

            return;
        }

        $batch = $result['batch'];

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
