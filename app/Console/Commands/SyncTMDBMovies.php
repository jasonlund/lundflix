<?php

namespace App\Console\Commands;

use App\Jobs\StoreTMDBData;
use App\Models\Movie;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class SyncTMDBMovies extends Command
{
    protected $signature = 'tmdb:sync-movies
        {--fresh : Re-sync all movies, including previously synced ones}
        {--limit=0 : Maximum number of movies to process (0 = unlimited)}';

    protected $description = 'Enrich movies with TMDB metadata (release date, production companies, languages)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $query = Movie::query()
            ->whereNotNull('imdb_id');

        if (! $this->option('fresh')) {
            $query->whereNull('tmdb_synced_at');
        }

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->info('All movies are already synced with TMDB.');

            return Command::SUCCESS;
        }

        $this->info("Dispatching jobs for {$total} movies...");

        $dispatched = 0;
        $progress = progress(label: 'Dispatching TMDB sync jobs', steps: $total);
        $progress->start();

        $query->chunkById(1000, function ($movies) use ($progress, &$dispatched, $limit) {
            foreach ($movies as $movie) {
                if ($limit > 0 && $dispatched >= $limit) {
                    return false;
                }

                StoreTMDBData::dispatch($movie);
                $dispatched++;
                $progress->advance();
            }

            if ($limit > 0 && $dispatched >= $limit) {
                return false;
            }
        });

        $progress->finish();

        $this->info("Dispatched {$dispatched} TMDB sync jobs to the queue.");

        return Command::SUCCESS;
    }
}
