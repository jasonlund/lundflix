<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlexMediaServer;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\PlexLibraryNotification;
use App\Services\ThirdParty\PlexService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PollPlexLibrary extends Command
{
    protected $signature = 'plex:poll-library';

    protected $description = 'Poll enabled Plex servers for recently added library items';

    /** @var array<string, array<string, mixed>|null> */
    private array $metadataCache = [];

    public function handle(PlexService $plex): int
    {
        $servers = PlexMediaServer::query()
            ->where('poll_recently_added', true)
            ->where('is_online', true)
            ->get();

        if ($servers->isEmpty()) {
            return Command::SUCCESS;
        }

        foreach ($servers as $server) {
            try {
                $this->pollServer($server, $plex);
            } catch (\Throwable $e) {
                Log::error('Plex poll failed for server', [
                    'server' => $server->name,
                    'client_identifier' => $server->client_identifier,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }

    private function pollServer(PlexMediaServer $server, PlexService $plex): void
    {
        $this->metadataCache = [];
        $cid = $server->client_identifier;

        $cache = Cache::store('redis');
        $hwm = (int) $cache->get("plex:poll:hwm:{$cid}", 0);

        if ($hwm === 0) {
            $hwm = now()->subMinutes(5)->timestamp;
            $cache->forever("plex:poll:hwm:{$cid}", $hwm);
        }

        $items = $plex->getRecentlyAdded($server);

        /** @var array<string> $lastKeys */
        $lastKeys = $cache->get("plex:poll:last-keys:{$cid}", []);

        $newItems = collect($items)
            ->filter(function (array $item) use ($hwm, $lastKeys): bool {
                $addedAt = $item['addedAt'] ?? 0;

                if ($addedAt < $hwm) {
                    return false;
                }

                if ($addedAt === $hwm) {
                    return ! in_array((string) ($item['ratingKey'] ?? ''), $lastKeys, true);
                }

                return true;
            })
            ->filter(fn (array $item): bool => in_array($item['type'] ?? '', ['movie', 'episode'], true));

        if ($newItems->isNotEmpty()) {
            $currentKeys = $newItems->map(fn (array $item): string => (string) ($item['ratingKey'] ?? ''))->values()->all();
            $keysTtl = max((int) config('services.plex.poll_hard_deadline_seconds', 900), 900) + 120;
            $cache->put("plex:poll:last-keys:{$cid}", $currentKeys, $keysTtl);
        }

        $movies = $newItems->filter(fn (array $item): bool => $item['type'] === 'movie');
        $episodes = $newItems->filter(fn (array $item): bool => $item['type'] === 'episode');

        /** @var Collection<int, array<string, mixed>> $readyItems */
        $readyItems = collect();

        foreach ($movies as $movie) {
            $readyItems->push($this->normalizeMovie($movie));
        }

        /** @var Collection<int, array<string, mixed>> $showEpisodes */
        foreach ($episodes->groupBy('grandparentRatingKey') as $grandparentKey => $showEpisodes) {
            $this->bufferEpisodes($server, (string) $grandparentKey, $showEpisodes);
        }

        $readyItems = $readyItems->merge($this->harvestRipeShows($server, $plex));

        if ($readyItems->isNotEmpty()) {
            $this->fulfillMatchingRequests($server, $readyItems, $plex);
            $this->sendSlackNotification($server, $readyItems);
        }

        if ($newItems->isNotEmpty()) {
            $maxAddedAt = $newItems->max(fn (array $item): int => $item['addedAt'] ?? 0);
            $cache->forever("plex:poll:hwm:{$cid}", $maxAddedAt);
        }
    }

    /**
     * @param  array<string, mixed>  $movie
     * @return array<string, mixed>
     */
    private function normalizeMovie(array $movie): array
    {
        return [
            'media_type' => 'movie',
            'title' => $movie['title'] ?? 'Unknown',
            'year' => $movie['year'] ?? null,
            'rating_key' => (string) ($movie['ratingKey'] ?? ''),
            'added_at' => $movie['addedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array<string, mixed>
     */
    private function normalizeEpisode(array $episode): array
    {
        return [
            'media_type' => 'episode',
            'title' => $episode['title'] ?? 'Unknown',
            'show_title' => $episode['grandparentTitle'] ?? 'Unknown',
            'season' => $episode['parentIndex'] ?? null,
            'episode_number' => $episode['index'] ?? null,
            'rating_key' => (string) ($episode['ratingKey'] ?? ''),
            'grandparent_rating_key' => (string) ($episode['grandparentRatingKey'] ?? ''),
            'added_at' => $episode['addedAt'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $episodes
     */
    private function bufferEpisodes(PlexMediaServer $server, string $grandparentKey, Collection $episodes): void
    {
        $cid = $server->client_identifier;
        $cache = Cache::store('redis');
        $bufferKey = "plex:poll:pending:{$cid}:{$grandparentKey}";
        $indexKey = "plex:poll:pending-index:{$cid}";

        $existing = $cache->get($bufferKey, []);
        $now = now()->timestamp;

        $normalized = $episodes->map(fn (array $ep): array => $this->normalizeEpisode($ep));

        if ($existing === []) {
            $firstEpisode = $episodes->first();
            $buffer = [
                'server_name' => $server->name,
                'show_title' => $firstEpisode['grandparentTitle'] ?? 'Unknown',
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'items' => $normalized->keyBy('rating_key')->all(),
            ];
        } else {
            $buffer = $existing;
            $newByKey = $normalized->keyBy('rating_key')->all();
            $addedNew = count(array_diff_key($newByKey, $buffer['items'])) > 0;

            if ($addedNew) {
                $buffer['last_seen_at'] = $now;
            }

            $buffer['items'] = $newByKey + $buffer['items'];
        }

        $cache->put($bufferKey, $buffer, 1800);

        $index = $cache->get($indexKey, []);
        if (! in_array($grandparentKey, $index, true)) {
            $index[] = $grandparentKey;
            $cache->put($indexKey, $index, 1800);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function harvestRipeShows(PlexMediaServer $server, PlexService $plex): Collection
    {
        $cid = $server->client_identifier;
        $cache = Cache::store('redis');
        $indexKey = "plex:poll:pending-index:{$cid}";
        $debounce = (int) config('services.plex.poll_debounce_seconds', 300);
        $hardDeadline = (int) config('services.plex.poll_hard_deadline_seconds', 900);
        $now = now()->timestamp;

        /** @var array<int, string> $index */
        $index = $cache->get($indexKey, []);

        if ($index === []) {
            return collect();
        }

        /** @var Collection<int, array<string, mixed>> $harvested */
        $harvested = collect();
        $remaining = [];

        foreach ($index as $grandparentKey) {
            $bufferKey = "plex:poll:pending:{$cid}:{$grandparentKey}";
            $buffer = $cache->get($bufferKey);

            if (! $buffer) {
                continue;
            }

            $sinceLastSeen = $now - ($buffer['last_seen_at'] ?? $now);
            $sinceFirstSeen = $now - ($buffer['first_seen_at'] ?? $now);

            if ($sinceLastSeen >= $debounce || $sinceFirstSeen >= $hardDeadline) {
                foreach ($buffer['items'] as $item) {
                    $harvested->push($item);
                }
                $cache->forget($bufferKey);
            } else {
                $remaining[] = $grandparentKey;
            }
        }

        if ($remaining === []) {
            $cache->forget($indexKey);
        } else {
            $cache->put($indexKey, $remaining, 1800);
        }

        return $harvested;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function fulfillMatchingRequests(PlexMediaServer $server, Collection $items, PlexService $plex): void
    {
        $fulfilled = DB::transaction(function () use ($server, $items, $plex): int {
            $count = 0;

            foreach ($items as $item) {
                $requestable = $this->resolveRequestable($server, $item, $plex);

                if (! $requestable) {
                    continue;
                }

                $count += RequestItem::pending()
                    ->where('requestable_type', $requestable::class)
                    ->where('requestable_id', $requestable->id)
                    ->update([
                        'status' => RequestItemStatus::Fulfilled,
                        'actioned_at' => now(),
                    ]);
            }

            return $count;
        });

        if ($fulfilled > 0) {
            Log::info('Plex poll auto-fulfilled request items', [
                'server' => $server->name,
                'fulfilled_count' => $fulfilled,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveRequestable(PlexMediaServer $server, array $item, PlexService $plex): Movie|Episode|null
    {
        return match ($item['media_type'] ?? null) {
            'movie' => $this->resolveMovie($server, $item, $plex),
            'episode' => $this->resolveEpisode($server, $item, $plex),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveMovie(PlexMediaServer $server, array $item, PlexService $plex): ?Movie
    {
        $metadata = $this->fetchMetadata($server, $item['rating_key'] ?? null, $plex);
        $identifiers = $metadata ? $plex->extractExternalIdentifiers($metadata) : [];

        if (isset($identifiers['tmdb'])) {
            $movie = Movie::query()->where('tmdb_id', (int) $identifiers['tmdb'])->first();

            if ($movie) {
                return $movie;
            }
        }

        if (isset($identifiers['imdb'])) {
            return Movie::query()->where('imdb_id', $identifiers['imdb'])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveEpisode(PlexMediaServer $server, array $item, PlexService $plex): ?Episode
    {
        $episodeMetadata = $this->fetchMetadata($server, $item['rating_key'] ?? null, $plex);
        $showIdentifiers = $episodeMetadata ? $plex->extractExternalIdentifiers($episodeMetadata) : [];

        if (! $this->hasShowIdentifiers($showIdentifiers) && isset($item['grandparent_rating_key'])) {
            $showMetadata = $this->fetchMetadata($server, $item['grandparent_rating_key'], $plex);

            if ($showMetadata) {
                $showIdentifiers = $plex->extractExternalIdentifiers($showMetadata);
            }
        }

        $show = $this->resolveShow($showIdentifiers);

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
     * @param  array<string, mixed>  $identifiers
     */
    private function resolveShow(array $identifiers): ?Show
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

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMetadata(PlexMediaServer $server, ?string $ratingKey, PlexService $plex): ?array
    {
        if ($ratingKey === null) {
            return null;
        }

        $cacheKey = "{$server->client_identifier}:{$ratingKey}";

        if (array_key_exists($cacheKey, $this->metadataCache)) {
            return $this->metadataCache[$cacheKey];
        }

        try {
            $this->metadataCache[$cacheKey] = $plex->fetchLibraryMetadata($server, $ratingKey);
        } catch (\Throwable $e) {
            Log::warning('Plex metadata fetch failed', [
                'server' => $server->name,
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
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function sendSlackNotification(PlexMediaServer $server, Collection $items): void
    {
        if (! config('services.slack.enabled')) {
            return;
        }

        $channel = config('services.slack.notifications.channel');

        if (! $channel) {
            return;
        }

        try {
            Notification::route('slack', $channel)
                ->notify(new PlexLibraryNotification(
                    serverName: $server->name,
                    items: $items,
                ));
        } catch (\Throwable $e) {
            Log::error('Plex poll Slack notification failed', [
                'server' => $server->name,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
