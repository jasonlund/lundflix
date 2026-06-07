<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Events\MediaAvailable;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Subscription;
use App\Services\Torrent\ApplyDownloadPlan;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMovieAvailability extends Command
{
    protected $signature = 'process:movie-availability';

    protected $description = 'Poll IPTorrents for subscribed movies and create requests once a torrent exists';

    private const LOOKBACK_DAYS = 3;

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
        $today = today();
        $windowStart = $today->copy()->subDays(self::LOOKBACK_DAYS);

        $subscriptions = Subscription::query()
            ->active()
            ->forMovies()
            ->with(['subscribable', 'user'])
            ->get()
            ->filter(function (Subscription $subscription) use ($windowStart, $today): bool {
                /** @var Movie $movie */
                $movie = $subscription->subscribable;

                if ($movie->status !== MovieStatus::Released) {
                    return false;
                }

                $release = $movie->digital_release_date;

                if (! $release) {
                    return false;
                }

                return $release->betweenIncluded($windowStart, $today);
            })
            ->values();

        $byMovie = $subscriptions->groupBy('subscribable_id');

        /** @var array<int, Movie> $toDispatch */
        $toDispatch = [];
        /** @var array<int, true> $dispatchedTorrentIds */
        $dispatchedTorrentIds = [];
        $processed = 0;

        foreach ($byMovie as $movieId => $subs) {
            /** @var Movie $movie */
            $movie = $subs->first()->subscribable;
            $movieHadDownload = false;

            foreach ($subs as $subscription) {
                $request = $this->createRequest->create($subscription->user);
                $this->createRequestItems->create($request, [
                    ['type' => MediaType::MOVIE, 'id' => $movie->id],
                ]);

                $request->load(['items.requestable' => function ($morphTo): void {
                    $morphTo->morphWith([
                        Episode::class => ['show.episodes'],
                    ]);
                }]);

                try {
                    $plan = $this->planner->plan($request);
                } catch (IptorrentsRateLimitExceededException) {
                    $this->warn('IPTorrents rate limit reached, stopping.');
                    break 2;
                } catch (IptorrentsAuthException $e) {
                    $this->warn($e->getMessage());
                    break 2;
                } catch (\Throwable $e) {
                    Log::warning('IPTorrents availability check failed', [
                        'movie_id' => $movie->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $this->applyDownloadPlan->apply($request, $plan, $dispatchedTorrentIds);

                $subscription->markFulfilled();
                $processed++;

                if ($plan->downloads !== []) {
                    $movieHadDownload = true;
                }
            }

            if ($movieHadDownload) {
                $toDispatch[$movieId] = $movie;
            }
        }

        foreach ($toDispatch as $movie) {
            MediaAvailable::dispatch(null, $movie);
        }

        $this->info("Processed {$processed} movie availability check(s).");

        return Command::SUCCESS;
    }
}
