<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Events\MediaAvailable;
use App\Events\MediaFoundInLibrary;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Jobs\DownloadTorrents;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\Subscription;
use App\Services\ThirdParty\PlexService;
use App\Services\TorrentFulfillmentService;
use App\Support\AirDateTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessShowAvailability extends Command
{
    protected $signature = 'process:show-availability';

    protected $description = 'Poll IPTorrents for subscribed shows and create requests once aired episodes have a torrent';

    private const LOOKBACK_HOURS = 24;

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
        private readonly TorrentFulfillmentService $fulfillment,
        private readonly PlexService $plex,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $windowStart = $now->copy()->subHours(self::LOOKBACK_HOURS);

        $windowStartDate = $windowStart->toDateString();
        $nowDate = $now->toDateString();

        $subscriptions = Subscription::query()
            ->active()
            ->downloads()
            ->forShows()
            ->with(['user', 'processedEpisodes'])
            ->get();

        $showIds = $subscriptions->pluck('subscribable_id')->unique()->values();

        if ($showIds->isEmpty()) {
            $this->info('Processed 0 show availability check(s).');

            return Command::SUCCESS;
        }

        // Pad ±1 day because airdates are date-only but the lookback window is a datetime; timezone offsets up to ±14h can shift the effective calendar day.
        $shows = Show::query()
            ->whereIn('id', $showIds)
            ->with(['episodes' => fn ($q) => $q
                ->whereDate('airdate', '>=', Carbon::parse($windowStartDate)->subDay()->toDateString())
                ->whereDate('airdate', '<=', Carbon::parse($nowDate)->addDay()->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $bySub = [];

        foreach ($subscriptions as $subscription) {
            /** @var Show|null $show */
            $show = $shows->get($subscription->subscribable_id);

            if (! $show) {
                continue;
            }

            $requestedIds = $subscription->processedEpisodes
                ->filter(fn (Episode $e): bool => $e->pivot->requested_at !== null) // @phpstan-ignore property.notFound
                ->pluck('id');

            $candidates = $show->episodes
                ->filter(function (Episode $episode) use ($show, $windowStart, $now, $requestedIds): bool {
                    if (! $episode->airdate) {
                        return false;
                    }

                    if ($requestedIds->contains($episode->id)) {
                        return false;
                    }

                    $airdatetime = AirDateTime::resolve(
                        $episode->airdate->format('Y-m-d'), // @phpstan-ignore method.nonObject (casted to Carbon)
                        $episode->airtime,
                        $show->web_channel, // @phpstan-ignore argument.type (casted to array)
                        $show->network, // @phpstan-ignore argument.type (casted to array)
                    );

                    return $airdatetime->greaterThan($windowStart) && $airdatetime->lessThanOrEqualTo($now);
                })
                ->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $bySub[] = [
                'subscription' => $subscription,
                'show' => $show,
                'candidates' => $candidates,
            ];
        }

        $processed = 0;

        /** @var array<int, array<int, Episode>> $foundInLibrary keyed by show id, episode id */
        $foundInLibrary = [];

        $libraryToken = config('services.plex.seed_token');

        if (! $libraryToken) {
            Log::warning('Plex library check skipped: seed token not configured.');
        } else {
            /** @var array<int, array<string, true>> $libraryEpisodes keyed by show id, then "season-number" */
            $libraryEpisodes = [];
            $remainingSubs = [];

            foreach ($bySub as $entry) {
                /** @var Show $show */
                $show = $entry['show'];
                /** @var Subscription $subscription */
                $subscription = $entry['subscription'];
                /** @var Collection<int, Episode> $candidates */
                $candidates = $entry['candidates'];

                if (! array_key_exists($show->id, $libraryEpisodes)) {
                    $libraryEpisodes[$show->id] = $show->imdb_id
                        ? $this->libraryEpisodeKeys($libraryToken, $show)
                        : [];
                }

                $keys = $libraryEpisodes[$show->id];

                if ($keys === []) {
                    $remainingSubs[] = $entry;

                    continue;
                }

                [$inLibrary, $remaining] = $candidates
                    ->partition(fn (Episode $e): bool => isset($keys[$e->season.'-'.$e->number]));

                if ($inLibrary->isNotEmpty()) {
                    $subscription->processedEpisodes()->syncWithoutDetaching(
                        $inLibrary->pluck('id')->mapWithKeys(fn ($id): array => [$id => ['requested_at' => now()]])->all(),
                    );

                    foreach ($inLibrary as $episode) {
                        $foundInLibrary[$show->id][$episode->id] = $episode;
                    }

                    $processed++;
                }

                if ($remaining->isNotEmpty()) {
                    $entry['candidates'] = $remaining->values();
                    $remainingSubs[] = $entry;
                }
            }

            $bySub = $remainingSubs;
        }

        $byShow = collect($bySub)->groupBy(fn ($e) => $e['show']->id);

        /** @var array<int, Collection<int, Episode>|null> $showAvailable keyed by show id */
        $showAvailable = [];
        /** @var array<int, array<int, Episode>> $newlyRequested keyed by show id, episode id */
        $newlyRequested = [];
        /** @var list<array{torrent_id: int, filename: string}> $torrentDownloads */
        $torrentDownloads = [];

        foreach ($bySub as $entry) {
            /** @var Show $show */
            $show = $entry['show'];
            /** @var Subscription $subscription */
            $subscription = $entry['subscription'];
            /** @var Collection<int, Episode> $candidates */
            $candidates = $entry['candidates'];

            if (! array_key_exists($show->id, $showAvailable)) {
                /** @var Collection<int, Episode|Movie> $allCandidates */
                $allCandidates = $byShow[$show->id]
                    ->flatMap(fn ($e) => $e['candidates'])
                    ->unique('id')
                    ->values();

                try {
                    $result = $this->fulfillment->fulfill($allCandidates);
                } catch (IptorrentsRateLimitExceededException) {
                    $this->warn('IPTorrents rate limit reached, stopping.');
                    break;
                } catch (IptorrentsAuthException $e) {
                    $this->warn($e->getMessage());
                    break;
                }

                foreach ($result->downloads as $download) {
                    $torrentDownloads[] = $download;
                }

                $showAvailable[$show->id] = $result->covered->isEmpty() ? null : $result->covered->values();
            }

            $available = $showAvailable[$show->id];

            if (! $available instanceof Collection || $available->isEmpty()) {
                continue;
            }

            $availableIds = $available->pluck('id')->all();

            $subAvailable = $candidates
                ->filter(fn (Episode $e): bool => in_array($e->id, $availableIds, true))
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            if ($subAvailable->isEmpty()) {
                continue;
            }

            $request = $this->createRequest->create($subscription->user);
            $this->createRequestItems->create(
                $request,
                $subAvailable->map(fn (Episode $e): array => ['type' => MediaType::EPISODE, 'id' => $e->id])->all(),
            );

            $subscription->processedEpisodes()->syncWithoutDetaching(
                $subAvailable->pluck('id')->mapWithKeys(fn ($id): array => [$id => ['requested_at' => now()]])->all(),
            );

            foreach ($subAvailable as $episode) {
                $newlyRequested[$show->id][$episode->id] = $episode;
            }

            $processed++;
        }

        foreach ($foundInLibrary as $showId => $episodesById) {
            /** @var Show $show */
            $show = $shows->get($showId);

            $episodes = collect(array_values($episodesById))
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            MediaFoundInLibrary::dispatch(null, $show, $episodes);
        }

        foreach ($newlyRequested as $showId => $episodesById) {
            /** @var Show $show */
            $show = $shows->get($showId);

            $episodes = collect(array_values($episodesById))
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            MediaAvailable::dispatch(null, $show, $episodes);
        }

        $torrentDownloads = collect($torrentDownloads)->unique('torrent_id')->values()->all();

        if ($torrentDownloads !== []) {
            DownloadTorrents::dispatch($torrentDownloads);
        }

        $this->info("Processed {$processed} show availability check(s).");

        return Command::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function libraryEpisodeKeys(string $token, Show $show): array
    {
        try {
            $servers = $this->plex->searchShowWithEpisodes($token, "imdb://{$show->imdb_id}");
        } catch (\Throwable $e) {
            Log::warning('Plex library check failed', [
                'show_id' => $show->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $keys = [];

        foreach ($servers as $server) {
            foreach ($server['episodes'] as $episode) {
                $keys[$episode['season'].'-'.$episode['episode']] = true;
            }
        }

        return $keys;
    }
}
