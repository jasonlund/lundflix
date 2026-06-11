<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Events\MediaAvailable;
use App\Events\MediaFoundInLibrary;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Jobs\DownloadTorrents;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Subscription;
use App\Services\ThirdParty\PlexService;
use App\Services\TorrentFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessMovieAvailability extends Command
{
    protected $signature = 'process:movie-availability';

    protected $description = 'Poll IPTorrents for subscribed movies and create requests once a torrent exists';

    private const LOOKBACK_DAYS = 3;

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
        $today = today();
        $windowStart = $today->copy()->subDays(self::LOOKBACK_DAYS);

        $subscriptions = Subscription::query()
            ->active()
            ->downloads()
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

        $libraryToken = config('services.plex.seed_token');

        if (! $libraryToken) {
            Log::warning('Plex library check skipped: seed token not configured.');
        }

        /** @var array<int, array{torrent_id: int, filename: string}|false> $checked */
        $checked = [];
        /** @var array<int, Movie> $toDispatch */
        $toDispatch = [];
        /** @var array<int, Movie> $foundInLibrary */
        $foundInLibrary = [];
        /** @var list<array{torrent_id: int, filename: string}> $torrentDownloads */
        $torrentDownloads = [];
        $processed = 0;

        foreach ($byMovie as $movieId => $subs) {
            /** @var Movie $movie */
            $movie = $subs->first()->subscribable;

            if ($libraryToken && $movie->imdb_id && $this->existsInLibrary($libraryToken, $movie)) {
                foreach ($subs as $subscription) {
                    $subscription->markFulfilled();
                    $processed++;
                }

                $foundInLibrary[$movieId] = $movie;

                continue;
            }

            if (! array_key_exists($movieId, $checked)) {
                /** @var Collection<int, Movie|Episode> $media */
                $media = collect([$movie]);

                try {
                    $result = $this->fulfillment->fulfill($media);
                } catch (IptorrentsRateLimitExceededException) {
                    $this->warn('IPTorrents rate limit reached, stopping.');
                    break;
                } catch (IptorrentsAuthException $e) {
                    $this->warn($e->getMessage());
                    break;
                }

                $checked[$movieId] = $result->downloads[0] ?? false;
            }

            if ($checked[$movieId] === false) {
                continue;
            }

            foreach ($subs as $subscription) {
                $request = $this->createRequest->create($subscription->user);
                $this->createRequestItems->create($request, [
                    ['type' => MediaType::MOVIE, 'id' => $movie->id],
                ], autoDownload: false);

                $subscription->markFulfilled();

                $processed++;
            }

            $torrentDownloads[] = $checked[$movieId];

            $toDispatch[$movieId] = $movie;
        }

        foreach ($foundInLibrary as $movie) {
            MediaFoundInLibrary::dispatch($movie);
        }

        foreach ($toDispatch as $movie) {
            MediaAvailable::dispatch(null, $movie);
        }

        if ($torrentDownloads !== []) {
            DownloadTorrents::dispatch($torrentDownloads);
        }

        $this->info("Processed {$processed} movie availability check(s).");

        return Command::SUCCESS;
    }

    private function existsInLibrary(string $token, Movie $movie): bool
    {
        try {
            return $this->plex
                ->searchByExternalId($token, "imdb://{$movie->imdb_id}", 1)
                ->isNotEmpty();
        } catch (\Throwable $e) {
            Log::warning('Plex library check failed', [
                'movie_id' => $movie->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
