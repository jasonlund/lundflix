<?php

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

        $result = Cache::lock("{$cacheKey}:lock", 10)->block(5, function () use ($cacheKey) {
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
            Log::warning('Plex webhook batch empty or expired', ['serverUuid' => $this->serverUuid]);

            return;
        }

        if ($result['action'] === 'release') {
            $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds', 30);
            $this->release($debounceSeconds);

            return;
        }

        $batch = $result['batch'];

        if (! config('services.slack.enabled')) {
            Log::warning('Plex batch notification skipped: Slack is not enabled', [
                'serverUuid' => $this->serverUuid,
                'itemCount' => count($batch['items']),
            ]);

            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            Log::warning('Plex batch notification skipped: channel not configured', [
                'serverUuid' => $this->serverUuid,
                'itemCount' => count($batch['items']),
            ]);

            return;
        }

        Log::info('Sending Plex batch notification', [
            'serverUuid' => $this->serverUuid,
            'serverName' => $batch['server_name'],
            'itemCount' => count($batch['items']),
            'channel' => $channel,
        ]);

        Notification::route('slack', $channel)
            ->notify(new PlexLibraryNotification(
                serverName: $batch['server_name'],
                items: collect($batch['items']),
            ));
    }
}
