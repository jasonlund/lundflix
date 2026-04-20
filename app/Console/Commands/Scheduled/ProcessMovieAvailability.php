<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Enums\ReleaseQuality;
use App\Events\MediaAvailable;
use App\Exceptions\PreDBRateLimitExceededException;
use App\Models\Movie;
use App\Models\Subscription;
use App\Services\PreDBService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMovieAvailability extends Command
{
    protected $signature = 'process:movie-availability';

    protected $description = 'Poll PreDB for subscribed movies and create requests once a quality release exists';

    private const LOOKBACK_DAYS = 3;

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
        private readonly PreDBService $predb,
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

        /** @var array<int, ReleaseQuality|false> $checked */
        $checked = [];
        /** @var array<int, array{movie: Movie, quality: ReleaseQuality}> $toDispatch */
        $toDispatch = [];
        $processed = 0;

        foreach ($byMovie as $movieId => $subs) {
            /** @var Movie $movie */
            $movie = $subs->first()->subscribable;

            if (! array_key_exists($movieId, $checked)) {
                try {
                    $checked[$movieId] = $this->predb->highestQuality($movie) ?? false;
                } catch (PreDBRateLimitExceededException) {
                    $this->warn('PreDB rate limit reached, stopping.');
                    break;
                } catch (\Throwable $e) {
                    Log::warning('PreDB availability check failed', [
                        'movie_id' => $movie->id,
                        'error' => $e->getMessage(),
                    ]);
                    $checked[$movieId] = false;
                }
            }

            $quality = $checked[$movieId];

            if ($quality === false) {
                continue;
            }

            foreach ($subs as $subscription) {
                $request = $this->createRequest->create($subscription->user);
                $this->createRequestItems->create($request, [
                    ['type' => MediaType::MOVIE, 'id' => $movie->id],
                ]);

                $subscription->markFulfilled();

                $processed++;
            }

            $toDispatch[$movieId] = ['movie' => $movie, 'quality' => $quality];
        }

        foreach ($toDispatch as $entry) {
            MediaAvailable::dispatch(null, $entry['movie'], null, $entry['quality']);
        }

        $this->info("Processed {$processed} movie availability check(s).");

        return Command::SUCCESS;
    }
}
