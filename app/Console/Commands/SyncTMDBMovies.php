<?php

namespace App\Console\Commands;

use App\Actions\Movie\UpsertTMDBData;
use App\Models\Movie;
use App\Services\TMDBService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

use function Laravel\Prompts\progress;

class SyncTMDBMovies extends Command
{
    private const BATCH_SIZE = 100;

    protected $signature = 'tmdb:sync-movies
        {--fresh : Re-sync all movies, including previously synced ones}
        {--limit=0 : Maximum number of movies to process (0 = unlimited)}';

    protected $description = 'Enrich movies with TMDB metadata (release date, production companies, languages)';

    private int $synced = 0;

    public function handle(TMDBService $tmdb, UpsertTMDBData $upsertTMDB): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('fresh')) {
            return $this->syncFresh($tmdb, $upsertTMDB, $limit);
        }

        // Phase 1: Sync movies that have never been synced
        $unsyncedCount = $this->syncUnsynced($tmdb, $upsertTMDB, $limit);

        // Phase 2: Update movies that recently changed on TMDB
        $remaining = $limit > 0 ? $limit - $this->synced : 0;
        if ($limit > 0 && $remaining <= 0) {
            return Command::SUCCESS;
        }

        $changedCount = $this->syncChanged($tmdb, $upsertTMDB, $remaining);

        if ($unsyncedCount === 0 && $changedCount === 0) {
            $this->info('All movies are up to date with TMDB.');
        }

        return Command::SUCCESS;
    }

    private function syncFresh(TMDBService $tmdb, UpsertTMDBData $upsertTMDB, int $limit): int
    {
        $query = Movie::query()->whereNotNull('imdb_id');

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->info('No movies to sync.');

            return Command::SUCCESS;
        }

        $this->syncWithFind($tmdb, $upsertTMDB, $query, $total, 'Syncing all movies with TMDB', $limit);

        return Command::SUCCESS;
    }

    private function syncUnsynced(TMDBService $tmdb, UpsertTMDBData $upsertTMDB, int $limit): int
    {
        $query = Movie::query()
            ->whereNotNull('imdb_id')
            ->whereNull('tmdb_synced_at');

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            return 0;
        }

        $this->syncWithFind($tmdb, $upsertTMDB, $query, $total, 'Syncing new movies with TMDB', $limit);

        return $total;
    }

    private function syncChanged(TMDBService $tmdb, UpsertTMDBData $upsertTMDB, int $limit): int
    {
        $this->info('Checking TMDB for recently changed movies...');

        $changedTmdbIds = $tmdb->changedMovieIds();

        if (empty($changedTmdbIds)) {
            return 0;
        }

        $query = Movie::query()
            ->whereNotNull('imdb_id')
            ->whereNotNull('tmdb_synced_at')
            ->whereIn('tmdb_id', $changedTmdbIds);

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->info('No recently changed movies to update.');

            return 0;
        }

        $this->syncWithDetails($tmdb, $upsertTMDB, $query, $total, 'Updating recently changed movies', $limit);

        return $total;
    }

    /**
     * Sync movies that need a find-by-IMDb-ID lookup first (no tmdb_id yet).
     *
     * @param  Builder<Movie>  $query
     */
    private function syncWithFind(TMDBService $tmdb, UpsertTMDBData $upsertTMDB, Builder $query, int $total, string $label, int $limit): void
    {
        $progress = progress(label: $label, steps: $total);
        $progress->start();

        $query->select(['id', 'imdb_id', 'title'])->chunkById(self::BATCH_SIZE, function (Collection $movies) use ($tmdb, $upsertTMDB, $progress, $limit) {
            if ($limit > 0) {
                $movies = $movies->take($limit - $this->synced);
            }

            $moviesByImdbId = $movies->keyBy('imdb_id');
            $imdbIds = $movies->pluck('imdb_id')->all();
            $now = now()->toDateTimeString();

            // Find all movies on TMDB concurrently
            $findResults = $tmdb->findManyByImdbId($imdbIds);

            // Fetch details for movies that were found
            $tmdbIdMap = [];
            foreach ($findResults as $imdbId => $result) {
                if ($result) {
                    $tmdbIdMap[$imdbId] = $result['id'];
                }
            }

            $detailsMap = [];
            if ($tmdbIdMap) {
                $details = $tmdb->movieDetailsMany(array_values(array_unique($tmdbIdMap)));
                foreach ($tmdbIdMap as $imdbId => $tmdbId) {
                    $detailsMap[$imdbId] = $details[$tmdbId] ?? null;
                }
            }

            // Build upsert data
            $upsertData = $this->buildUpsertData($imdbIds, $moviesByImdbId, $tmdbIdMap, $detailsMap, $now);
            $upsertTMDB->upsert($upsertData);

            $this->synced += count($imdbIds);
            $progress->advance(count($imdbIds));

            if ($limit > 0 && $this->synced >= $limit) {
                return false;
            }
        });

        $progress->finish();
        $this->info("Synced {$this->synced} movies with TMDB.");
    }

    /**
     * Sync movies that already have a tmdb_id (skip the find step).
     *
     * @param  Builder<Movie>  $query
     */
    private function syncWithDetails(TMDBService $tmdb, UpsertTMDBData $upsertTMDB, Builder $query, int $total, string $label, int $limit): void
    {
        $synced = 0;
        $progress = progress(label: $label, steps: $total);
        $progress->start();

        $query->select(['id', 'imdb_id', 'tmdb_id', 'title'])->chunkById(self::BATCH_SIZE, function (Collection $movies) use ($tmdb, $upsertTMDB, $progress, $limit, &$synced) {
            if ($limit > 0) {
                $movies = $movies->take($limit - $synced);
            }

            $tmdbIds = $movies->pluck('tmdb_id')->all();
            $now = now()->toDateTimeString();

            // Already have tmdb_ids — go straight to details
            $details = $tmdb->movieDetailsMany($tmdbIds);

            // Build upsert data
            $upsertData = [];
            foreach ($movies as $movie) {
                $movieDetails = $details[$movie->tmdb_id] ?? null;

                $row = [
                    'imdb_id' => $movie->imdb_id,
                    'title' => $movie->title,
                    'tmdb_id' => $movie->tmdb_id,
                    'tmdb_synced_at' => $now,
                    'release_date' => null,
                    'digital_release_date' => null,
                    'production_companies' => null,
                    'spoken_languages' => null,
                    'alternative_titles' => null,
                    'original_language' => null,
                    'original_title' => null,
                    'tagline' => null,
                    'status' => null,
                    'budget' => null,
                    'revenue' => null,
                    'origin_country' => null,
                    'release_dates' => null,
                ];

                if ($movieDetails) {
                    $row = array_merge($row, UpsertTMDBData::mapFromApi($movieDetails));
                }

                $upsertData[] = $row;
            }

            $upsertTMDB->upsert($upsertData);

            $count = $movies->count();
            $synced += $count;
            $progress->advance($count);

            if ($limit > 0 && $synced >= $limit) {
                return false;
            }
        });

        $progress->finish();
        $this->info("Updated {$synced} recently changed movies.");
    }

    /**
     * @param  array<int, string>  $imdbIds
     * @param  Collection<string, Movie>  $moviesByImdbId
     * @param  array<string, int>  $tmdbIdMap
     * @param  array<string, array<string, mixed>|null>  $detailsMap
     * @return array<int, array<string, mixed>>
     */
    private function buildUpsertData(array $imdbIds, Collection $moviesByImdbId, array $tmdbIdMap, array $detailsMap, string $now): array
    {
        $upsertData = [];

        foreach ($imdbIds as $imdbId) {
            $row = [
                'imdb_id' => $imdbId,
                'title' => $moviesByImdbId[$imdbId]->title,
                'tmdb_synced_at' => $now,
                'tmdb_id' => null,
                'release_date' => null,
                'digital_release_date' => null,
                'production_companies' => null,
                'spoken_languages' => null,
                'alternative_titles' => null,
                'original_language' => null,
                'original_title' => null,
                'tagline' => null,
                'status' => null,
                'budget' => null,
                'revenue' => null,
                'origin_country' => null,
                'release_dates' => null,
            ];

            if (isset($detailsMap[$imdbId]) && $detailsMap[$imdbId]) {
                $row = array_merge($row, UpsertTMDBData::mapFromApi($detailsMap[$imdbId]));
            } elseif (isset($tmdbIdMap[$imdbId])) {
                $row['tmdb_id'] = $tmdbIdMap[$imdbId];
            }

            $upsertData[] = $row;
        }

        return $upsertData;
    }
}
