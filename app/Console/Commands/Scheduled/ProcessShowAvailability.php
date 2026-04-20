<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Enums\ReleaseQuality;
use App\Events\MediaAvailable;
use App\Exceptions\PreDBRateLimitExceededException;
use App\Models\Episode;
use App\Models\Show;
use App\Models\Subscription;
use App\Services\PreDBService;
use App\Support\AirDateTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessShowAvailability extends Command
{
    protected $signature = 'process:show-availability';

    protected $description = 'Poll PreDB for subscribed shows and create requests once aired episodes have a quality release';

    private const LOOKBACK_HOURS = 24;

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
        private readonly PreDBService $predb,
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
            ->forShows()
            ->with(['user', 'processedEpisodes:id'])
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

            $processedIds = $subscription->processedEpisodes->pluck('id')->all();

            $candidates = $show->episodes
                ->filter(function (Episode $episode) use ($show, $windowStart, $now, $processedIds): bool {
                    if (! $episode->airdate) {
                        return false;
                    }

                    if (in_array($episode->id, $processedIds, true)) {
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

        $byShow = collect($bySub)->groupBy(fn ($e) => $e['show']->id);

        /** @var array<int, Collection<int, Episode>|null> $showAvailable keyed by show id */
        $showAvailable = [];
        /** @var array<int, array<int, Episode>> $newlyRequested keyed by show id, episode id */
        $newlyRequested = [];
        $processed = 0;

        foreach ($bySub as $entry) {
            /** @var Show $show */
            $show = $entry['show'];
            /** @var Subscription $subscription */
            $subscription = $entry['subscription'];
            /** @var Collection<int, Episode> $candidates */
            $candidates = $entry['candidates'];

            if (! array_key_exists($show->id, $showAvailable)) {
                $allCandidates = $byShow[$show->id]
                    ->flatMap(fn ($e) => $e['candidates'])
                    ->unique('id')
                    ->values();

                try {
                    $showAvailable[$show->id] = $this->predb->findAvailableEpisodes($show, $allCandidates);
                } catch (PreDBRateLimitExceededException) {
                    $this->warn('PreDB rate limit reached, stopping.');
                    break;
                } catch (\Throwable $e) {
                    Log::warning('PreDB availability check failed', [
                        'show_id' => $show->id,
                        'error' => $e->getMessage(),
                    ]);
                    $showAvailable[$show->id] = null;
                }
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

            $subscription->processedEpisodes()->attach($subAvailable->pluck('id')->all());

            foreach ($subAvailable as $episode) {
                $newlyRequested[$show->id][$episode->id] = $episode;
            }

            $processed++;
        }

        foreach ($newlyRequested as $showId => $episodesById) {
            /** @var Show $show */
            $show = $shows->get($showId);

            $episodes = collect(array_values($episodesById))
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            $bestQuality = $episodes
                ->map(fn (Episode $e) => $e->predb_quality ?? null)
                ->filter()
                ->reduce(fn (?ReleaseQuality $carry, ReleaseQuality $q): ReleaseQuality => ! $carry instanceof ReleaseQuality || $q->value > $carry->value ? $q : $carry);

            MediaAvailable::dispatch(null, $show, $episodes, $bestQuality);
        }

        $this->info("Processed {$processed} show availability check(s).");

        return Command::SUCCESS;
    }
}
