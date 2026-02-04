<?php

namespace App\Console\Commands;

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

use function Laravel\Prompts\progress;

class SyncFanartUpdates extends Command
{
    protected $signature = 'fanart:sync-updates {--fresh : Ignore last sync timestamp}';

    protected $description = 'Sync updated artwork from fanart.tv';

    private const CACHE_KEY = 'fanart:last_sync_timestamp';

    private const RATE_LIMIT_DELAY_MS = 250;

    public function handle(FanartTVService $fanart): int
    {
        $since = $this->option('fresh') ? null : Cache::get(self::CACHE_KEY);
        $syncStartTime = now()->timestamp;

        $this->info('Fetching recently updated movies...');
        $updatedMovieIds = $fanart->latestMovies($since);

        $this->info('Fetching recently updated shows...');
        $updatedShowIds = $fanart->latestShows($since);

        $movies = Movie::query()
            ->whereIn('imdb_id', $updatedMovieIds)
            ->get();

        $shows = Show::query()
            ->whereIn('thetvdb_id', $updatedShowIds)
            ->get();

        $totalToProcess = $movies->count() + $shows->count();

        if ($totalToProcess === 0) {
            $this->info('No updates found for local media.');
            Cache::forever(self::CACHE_KEY, $syncStartTime);

            return Command::SUCCESS;
        }

        $this->info("Found {$movies->count()} movies and {$shows->count()} shows with updates.");

        $progress = progress(label: 'Processing updates', steps: $totalToProcess);
        $progress->start();

        foreach ($movies as $movie) {
            $this->processMovie($fanart, $movie);
            $progress->advance();
            usleep(self::RATE_LIMIT_DELAY_MS * 1000);
        }

        foreach ($shows as $show) {
            $this->processShow($fanart, $show);
            $progress->advance();
            usleep(self::RATE_LIMIT_DELAY_MS * 1000);
        }

        $progress->finish();

        Cache::forever(self::CACHE_KEY, $syncStartTime);

        $this->info("Sync complete. Processed {$totalToProcess} items.");

        return Command::SUCCESS;
    }

    private function processMovie(FanartTVService $fanart, Movie $movie): void
    {
        try {
            $response = $fanart->movie($movie->imdb_id);

            if ($response !== null) {
                StoreFanart::dispatch($movie, $response);
            }
        } catch (\Throwable $e) {
            $this->warn("Failed to fetch artwork for movie {$movie->imdb_id}: {$e->getMessage()}");
        }
    }

    private function processShow(FanartTVService $fanart, Show $show): void
    {
        try {
            $response = $fanart->show($show->thetvdb_id);

            if ($response !== null) {
                StoreFanart::dispatch($show, $response);
            }
        } catch (\Throwable $e) {
            $this->warn("Failed to fetch artwork for show {$show->thetvdb_id}: {$e->getMessage()}");
        }
    }
}
