<?php

namespace App\Jobs;

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\RequestItem;
use App\Models\Show;
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
        $items = collect($batch['items']);

        $this->fulfillMatchingRequests($items);
        $this->sendSlackNotification($batch, $items);
    }

    private function fulfillMatchingRequests(\Illuminate\Support\Collection $items): void
    {
        $fulfilled = 0;

        foreach ($items as $item) {
            $fulfilled += match ($item['media_type']) {
                'movie' => $this->fulfillMovie($item),
                'episode' => $this->fulfillEpisode($item),
                default => 0,
            };
        }

        if ($fulfilled > 0) {
            Log::info('Plex webhook auto-fulfilled request items', [
                'serverUuid' => $this->serverUuid,
                'fulfilledCount' => $fulfilled,
            ]);
        }
    }

    private function fulfillMovie(array $item): int
    {
        $movie = Movie::query()
            ->where('title', $item['title'])
            ->where('year', $item['year'])
            ->first();

        if (! $movie) {
            return 0;
        }

        return RequestItem::pending()
            ->where('requestable_type', Movie::class)
            ->where('requestable_id', $movie->id)
            ->update([
                'status' => RequestItemStatus::Fulfilled,
                'actioned_at' => now(),
            ]);
    }

    private function fulfillEpisode(array $item): int
    {
        $show = Show::query()->where('name', $item['show_title'])->first();

        if (! $show) {
            return 0;
        }

        $episode = Episode::query()
            ->where('show_id', $show->id)
            ->where('season', $item['season'])
            ->where('number', $item['episode_number'])
            ->first();

        if (! $episode) {
            return 0;
        }

        return RequestItem::pending()
            ->where('requestable_type', Episode::class)
            ->where('requestable_id', $episode->id)
            ->update([
                'status' => RequestItemStatus::Fulfilled,
                'actioned_at' => now(),
            ]);
    }

    private function sendSlackNotification(array $batch, \Illuminate\Support\Collection $items): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Plex batch notification skipped: Slack is not enabled', [
                'serverUuid' => $this->serverUuid,
                'itemCount' => $items->count(),
            ]);

            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            Log::warning('Plex batch notification skipped: channel not configured', [
                'serverUuid' => $this->serverUuid,
                'itemCount' => $items->count(),
            ]);

            return;
        }

        Log::info('Sending Plex batch notification', [
            'serverUuid' => $this->serverUuid,
            'serverName' => $batch['server_name'],
            'itemCount' => $items->count(),
            'channel' => $channel,
        ]);

        Notification::route('slack', $channel)
            ->notify(new PlexLibraryNotification(
                serverName: $batch['server_name'],
                items: $items,
            ));
    }
}
