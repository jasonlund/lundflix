<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlexMediaServer;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\PlexLibraryNotification;
use App\Services\ThirdParty\PlexService;
use App\Support\PlexWebhookBatchStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Nightwatch\Compatibility;

class ProcessPlexWebhookBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $metadataCache = [];

    public function __construct(
        public string $serverUuid,
        public string $groupKey,
        public int $version,
    ) {
        $this->onQueue((string) config('services.plex.webhook_queue', 'plex-webhooks'));
    }

    public function handle(PlexWebhookBatchStore $batchStore, PlexService $plex): void
    {
        $processingResult = $batchStore->withProcessingLock($this->serverUuid, $this->groupKey, function () use ($batchStore, $plex): string {
            $snapshot = $batchStore->withBatchLock($this->serverUuid, $this->groupKey, function () use ($batchStore): array {
                $batch = $batchStore->get($this->serverUuid, $this->groupKey);

                if (! $batch) {
                    return ['status' => 'missing'];
                }

                $context = $this->contextForBatch($batch);

                if ((int) ($batch['version'] ?? 0) !== $this->version) {
                    Log::info('Plex webhook batch flush skipped: stale job', $context);

                    return ['status' => 'stale'];
                }

                if (now()->timestamp < (int) ($batch['flush_at'] ?? 0)) {
                    Log::info('Plex webhook batch flush skipped: early job', $context);

                    return ['status' => 'early'];
                }

                return [
                    'status' => 'process',
                    'batch' => $batch,
                    'item_keys' => array_keys($batch['items'] ?? []),
                ];
            });

            if ($snapshot['status'] !== 'process') {
                if ($snapshot['status'] === 'missing') {
                    Log::info('Plex webhook batch flush skipped: batch missing', [
                        'server_uuid' => $this->serverUuid,
                        'group_key' => $this->groupKey,
                        'version' => $this->version,
                        ...$this->traceContext(),
                    ]);
                }

                return $snapshot['status'];
            }

            /** @var array<string, mixed> $batch */
            $batch = $snapshot['batch'];
            /** @var list<string> $itemKeys */
            $itemKeys = $snapshot['item_keys'];
            /** @var Collection<string, array<string, mixed>> $items */
            $items = collect($batch['items'] ?? []);
            $context = $this->contextForBatch($batch);

            Log::info('Plex webhook batch flush started', $context);

            $server = PlexMediaServer::query()
                ->where('client_identifier', $this->serverUuid)
                ->first();

            $fulfilledCount = $this->fulfillMatchingRequests($server, $items, $plex, $context);

            if ($fulfilledCount > 0) {
                Log::info('Plex webhook auto-fulfilled request items', [
                    ...$context,
                    'fulfilled_count' => $fulfilledCount,
                ]);
            }

            $this->sendSlackNotification($batch, $items->values(), $context);

            $remainingBatch = $batchStore->finalizeProcessedItems(
                $this->serverUuid,
                $this->groupKey,
                $this->version,
                $itemKeys,
            );

            Log::info('Plex webhook batch flush completed', [
                ...$context,
                'fulfilled_count' => $fulfilledCount,
                'remaining_item_count' => $remainingBatch ? count($remainingBatch['items']) : 0,
            ]);

            return 'processed';
        });

        if ($processingResult === null) {
            Log::info('Plex webhook batch flush skipped: processing lock busy', [
                'server_uuid' => $this->serverUuid,
                'group_key' => $this->groupKey,
                'version' => $this->version,
                ...$this->traceContext(),
            ]);
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     */
    private function fulfillMatchingRequests(?PlexMediaServer $server, Collection $items, PlexService $plex, array $context): int
    {
        return DB::transaction(function () use ($server, $items, $plex, $context): int {
            $fulfilled = 0;

            foreach ($items as $item) {
                $requestable = $this->resolveRequestable($server, $item, $plex, $context);

                if (! $requestable) {
                    continue;
                }

                $fulfilled += RequestItem::pending()
                    ->where('requestable_type', $requestable::class)
                    ->where('requestable_id', $requestable->id)
                    ->update([
                        'status' => RequestItemStatus::Fulfilled,
                        'actioned_at' => now(),
                    ]);
            }

            return $fulfilled;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $context
     */
    private function resolveRequestable(?PlexMediaServer $server, array $item, PlexService $plex, array $context): Movie|Episode|null
    {
        return match ($item['media_type'] ?? null) {
            'movie' => $this->resolveMovie($server, $item, $plex, $context),
            'episode' => $this->resolveEpisode($server, $item, $plex, $context),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $context
     */
    private function resolveMovie(?PlexMediaServer $server, array $item, PlexService $plex, array $context): ?Movie
    {
        $metadata = $this->fetchMetadata($server, $item['rating_key'] ?? null, $plex, $context);
        $identifiers = $metadata ? $plex->extractExternalIdentifiers($metadata) : [];

        if (isset($identifiers['tmdb'])) {
            $movie = Movie::query()->where('tmdb_id', (int) $identifiers['tmdb'])->first();

            if ($movie) {
                return $movie;
            }
        }

        if (isset($identifiers['imdb'])) {
            $movie = Movie::query()->where('imdb_id', $identifiers['imdb'])->first();

            if ($movie) {
                return $movie;
            }
        }

        return Movie::query()
            ->where('title', (string) ($item['title'] ?? ''))
            ->where('year', $item['year'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $context
     */
    private function resolveEpisode(?PlexMediaServer $server, array $item, PlexService $plex, array $context): ?Episode
    {
        $episodeMetadata = $this->fetchMetadata($server, $item['rating_key'] ?? null, $plex, $context);
        $showIdentifiers = $episodeMetadata ? $plex->extractExternalIdentifiers($episodeMetadata) : [];

        if (! $this->hasShowIdentifiers($showIdentifiers) && isset($item['grandparent_rating_key'])) {
            $showMetadata = $this->fetchMetadata($server, $item['grandparent_rating_key'], $plex, $context);

            if ($showMetadata) {
                $showIdentifiers = $plex->extractExternalIdentifiers($showMetadata);
            }
        }

        $show = $this->resolveShow($item, $showIdentifiers);

        if (! $show instanceof Show) {
            return null;
        }

        return Episode::query()
            ->where('show_id', $show->id)
            ->where('season', $item['season'])
            ->where('number', $item['episode_number'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $identifiers
     */
    private function resolveShow(array $item, array $identifiers): ?Show
    {
        if (isset($identifiers['tmdb'])) {
            $show = Show::query()->where('tmdb_id', (int) $identifiers['tmdb'])->first();

            if ($show) {
                return $show;
            }
        }

        if (isset($identifiers['imdb'])) {
            $show = Show::query()->where('imdb_id', $identifiers['imdb'])->first();

            if ($show) {
                return $show;
            }
        }

        if (isset($identifiers['tvdb'])) {
            $show = Show::query()->where('thetvdb_id', (int) $identifiers['tvdb'])->first();

            if ($show) {
                return $show;
            }
        }

        return Show::query()
            ->where('name', (string) ($item['show_title'] ?? ''))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function fetchMetadata(?PlexMediaServer $server, ?string $ratingKey, PlexService $plex, array $context): ?array
    {
        if ($ratingKey === null) {
            return null;
        }

        if (! $server instanceof PlexMediaServer) {
            Log::warning('Plex metadata enrichment failed: source server missing', [
                ...$context,
                'rating_key' => $ratingKey,
            ]);

            return null;
        }

        $cacheKey = "{$server->client_identifier}:{$ratingKey}";

        if (array_key_exists($cacheKey, $this->metadataCache)) {
            return $this->metadataCache[$cacheKey];
        }

        try {
            $this->metadataCache[$cacheKey] = $plex->fetchMetadataForWebhookItem($server, $ratingKey);
        } catch (\Throwable $e) {
            Log::warning('Plex metadata enrichment failed', [
                ...$context,
                'rating_key' => $ratingKey,
                'error' => $e->getMessage(),
            ]);

            $this->metadataCache[$cacheKey] = null;
        }

        return $this->metadataCache[$cacheKey];
    }

    /**
     * @param  array<string, mixed>  $identifiers
     */
    private function hasShowIdentifiers(array $identifiers): bool
    {
        return isset($identifiers['tmdb']) || isset($identifiers['imdb']) || isset($identifiers['tvdb']);
    }

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, mixed>
     */
    private function contextForBatch(array $batch): array
    {
        return [
            'server_uuid' => $batch['server_uuid'],
            'group_key' => $batch['group_key'],
            'version' => $batch['version'],
            'item_count' => count($batch['items'] ?? []),
            ...$this->traceContext(),
        ];
    }

    /**
     * @param  array<string, mixed>  $batch
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     */
    private function sendSlackNotification(array $batch, Collection $items, array $context): void
    {
        if (! config('services.slack.enabled')) {
            Log::warning('Plex batch notification skipped: Slack is not enabled', $context);

            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            Log::warning('Plex batch notification skipped: channel not configured', $context);

            return;
        }

        Log::info('Sending Plex batch notification', [
            ...$context,
            'channel' => $channel,
        ]);

        Notification::route('slack', $channel)
            ->notify(new PlexLibraryNotification(
                serverName: $batch['server_name'],
                items: $items,
            ));
    }

    /**
     * @return array<string, string>
     */
    private function traceContext(): array
    {
        $traceId = class_exists(Compatibility::class)
            ? Compatibility::getTraceIdFromContext()
            : null;

        return $traceId ? ['trace_id' => $traceId] : [];
    }
}
