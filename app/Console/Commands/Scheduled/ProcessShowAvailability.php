<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Events\MediaAvailable;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\Subscription;
use App\Services\Torrent\ApplyDownloadPlan;
use App\Services\Torrent\RequestDownloadPlanner;
use App\Support\AirDateTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessShowAvailability extends Command
{
    protected $signature = 'process:show-availability';

    protected $description = 'Poll IPTorrents for subscribed shows and create requests once aired episodes have a torrent';

    private const LOOKBACK_HOURS = 24;

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
        private readonly RequestDownloadPlanner $planner,
        private readonly ApplyDownloadPlan $applyDownloadPlan,
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

        /** @var array<int, array<int, Episode>> $newlyAvailable keyed by show id, episode id */
        $newlyAvailable = [];
        $processed = 0;

        foreach ($bySub as $entry) {
            /** @var Show $show */
            $show = $entry['show'];
            /** @var Subscription $subscription */
            $subscription = $entry['subscription'];
            /** @var \Illuminate\Support\Collection<int, Episode> $candidates */
            $candidates = $entry['candidates'];

            $request = $this->createRequest->create($subscription->user);
            $this->createRequestItems->create(
                $request,
                $candidates->map(fn (Episode $e): array => ['type' => MediaType::EPISODE, 'id' => $e->id])->all(),
            );

            $request->load(['items.requestable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Episode::class => ['show.episodes'],
                ]);
            }]);

            try {
                $plan = $this->planner->plan($request);
            } catch (IptorrentsRateLimitExceededException) {
                $this->warn('IPTorrents rate limit reached, stopping.');
                break;
            } catch (IptorrentsAuthException $e) {
                $this->warn($e->getMessage());
                break;
            } catch (\Throwable $e) {
                Log::warning('IPTorrents availability check failed', [
                    'show_id' => $show->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $this->applyDownloadPlan->apply($request, $plan);

            $unavailableItemIds = [];
            foreach ($plan->notFound as $item) {
                $unavailableItemIds[$item->id] = true;
            }
            foreach ($plan->oversize as $item) {
                $unavailableItemIds[$item->id] = true;
            }

            /** @var array<int, Episode> $availableEpisodes */
            $availableEpisodes = [];
            foreach ($request->items as $item) {
                /** @var RequestItem $item */
                if (isset($unavailableItemIds[$item->id])) {
                    continue;
                }

                $episode = $item->requestable;

                if ($episode instanceof Episode) {
                    $availableEpisodes[$episode->id] = $episode;
                }
            }

            if ($availableEpisodes === []) {
                continue;
            }

            $subscription->processedEpisodes()->syncWithoutDetaching(
                collect(array_keys($availableEpisodes))
                    ->mapWithKeys(fn ($id): array => [$id => ['requested_at' => now()]])
                    ->all(),
            );

            foreach ($availableEpisodes as $id => $episode) {
                $newlyAvailable[$show->id][$id] = $episode;
            }

            $processed++;
        }

        foreach ($newlyAvailable as $showId => $episodesById) {
            /** @var Show $show */
            $show = $shows->get($showId);

            $episodes = collect(array_values($episodesById))
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            MediaAvailable::dispatch(null, $show, $episodes);
        }

        $this->info("Processed {$processed} show availability check(s).");

        return Command::SUCCESS;
    }
}
