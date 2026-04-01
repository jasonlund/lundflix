<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\TMDB\UpsertTMDBImages;
use App\Actions\TMDB\UpsertTMDBShowData;
use App\Models\Show;
use App\Services\ThirdParty\TMDBService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

use function Laravel\Prompts\progress;

class SyncTMDBShows extends Command
{
    private const BATCH_SIZE = 100;

    protected $signature = 'tmdb:sync-shows
        {--fresh : Re-sync all shows, including previously synced ones}
        {--limit=0 : Maximum number of shows to process (0 = unlimited)}';

    protected $description = 'Enrich shows with TMDB metadata and images';

    private int $synced = 0;

    public function handle(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('fresh')) {
            return $this->syncFresh($tmdb, $upsertTMDB, $limit);
        }

        $unsyncedCount = $this->syncUnsynced($tmdb, $upsertTMDB, $limit);

        $remaining = $limit > 0 ? $limit - $this->synced : 0;
        if ($limit > 0 && $remaining <= 0) {
            return Command::SUCCESS;
        }

        $changedCount = $this->syncChanged($tmdb, $upsertTMDB, $remaining);

        if ($unsyncedCount === 0 && $changedCount === 0) {
            $this->info('All shows are up to date with TMDB.');
        }

        return Command::SUCCESS;
    }

    private function syncFresh(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB, int $limit): int
    {
        $query = Show::query()->where(function (Builder $q) {
            $q->whereNotNull('imdb_id')->orWhereNotNull('thetvdb_id');
        });

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->info('No shows to sync.');

            return Command::SUCCESS;
        }

        $this->syncWithFind($tmdb, $upsertTMDB, $query, $total, 'Syncing all shows with TMDB', $limit);

        return Command::SUCCESS;
    }

    private function syncUnsynced(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB, int $limit): int
    {
        $query = Show::query()
            ->where(function (Builder $q) {
                $q->whereNotNull('imdb_id')->orWhereNotNull('thetvdb_id');
            })
            ->whereNull('tmdb_synced_at');

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            return 0;
        }

        $this->syncWithFind($tmdb, $upsertTMDB, $query, $total, 'Syncing new shows with TMDB', $limit);

        return $total;
    }

    private function syncChanged(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB, int $limit): int
    {
        $this->info('Checking TMDB for recently changed shows...');

        $changedTmdbIds = $tmdb->changedShowIds();

        if (empty($changedTmdbIds)) {
            return 0;
        }

        $query = Show::query()
            ->whereNotNull('tmdb_synced_at')
            ->whereIn('tmdb_id', $changedTmdbIds);

        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->info('No recently changed shows to update.');

            return 0;
        }

        $this->syncWithDetails($tmdb, $upsertTMDB, $query, $total, 'Updating recently changed shows', $limit);

        return $total;
    }

    /**
     * @param  Builder<Show>  $query
     */
    private function syncWithFind(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB, Builder $query, int $total, string $label, int $limit): void
    {
        $progress = progress(label: $label, steps: $total);
        $progress->start();

        $query->select(['id', 'tvmaze_id', 'imdb_id', 'thetvdb_id', 'name'])->chunkById(self::BATCH_SIZE, function (Collection $shows) use ($tmdb, $upsertTMDB, $progress, $limit) {
            if ($limit > 0) {
                $shows = $shows->take($limit - $this->synced);
            }

            $showsByTvmazeId = $shows->keyBy('tvmaze_id');

            // Find shows on TMDB via IMDb ID
            $imdbShows = $shows->filter(fn (Show $s): bool => $s->imdb_id !== null);
            $imdbIds = $imdbShows->pluck('imdb_id')->all();
            $findResults = $imdbIds ? $tmdb->findManyShowsByExternalId($imdbIds) : [];

            // For shows not found via IMDb, try TheTVDB
            $tvdbFallbacks = $shows->filter(function (Show $s) use ($findResults): bool {
                return ($s->imdb_id === null || ($findResults[$s->imdb_id] ?? null) === null) && $s->thetvdb_id !== null;
            });

            $tvdbIds = $tvdbFallbacks->pluck('thetvdb_id')->map(fn ($id) => (string) $id)->all();
            $tvdbResults = $tvdbIds ? $tmdb->findManyShowsByExternalId($tvdbIds, 'tvdb_id') : [];

            // Build tmdb_id map
            $tmdbIdMap = [];
            foreach ($shows as $show) {
                $result = $findResults[$show->imdb_id] ?? null;
                if ($result) {
                    $tmdbIdMap[$show->tvmaze_id] = $result['id'];

                    continue;
                }

                if ($show->thetvdb_id !== null) {
                    $tvdbResult = $tvdbResults[(string) $show->thetvdb_id] ?? null;
                    if ($tvdbResult) {
                        $tmdbIdMap[$show->tvmaze_id] = $tvdbResult['id'];
                    }
                }
            }

            // Fetch details for found shows
            $detailsMap = [];
            if ($tmdbIdMap) {
                $details = $tmdb->showDetailsMany(array_values(array_unique($tmdbIdMap)));
                foreach ($tmdbIdMap as $tvmazeId => $tmdbId) {
                    $detailsMap[$tvmazeId] = $details[$tmdbId] ?? null;
                }
            }

            // Build upsert data
            $now = now()->toDateTimeString();
            $upsertData = [];
            foreach ($shows as $show) {
                $row = [
                    'tvmaze_id' => $show->tvmaze_id,
                    'name' => $show->name,
                    'tmdb_synced_at' => $now,
                    'tmdb_id' => null,
                    'content_ratings' => null,
                    'original_name' => null,
                    'original_language' => null,
                    'thetvdb_id' => $show->thetvdb_id,
                ];

                $details = $detailsMap[$show->tvmaze_id] ?? null;
                if ($details) {
                    $mapped = UpsertTMDBShowData::mapFromApi($details);
                    $row = array_merge($row, $mapped);
                    // Backfill thetvdb_id from TMDB only if missing locally
                    if ($show->thetvdb_id === null && isset($details['external_ids']['tvdb_id'])) {
                        $row['thetvdb_id'] = $details['external_ids']['tvdb_id'];
                    }
                } elseif (isset($tmdbIdMap[$show->tvmaze_id])) {
                    $row['tmdb_id'] = $tmdbIdMap[$show->tvmaze_id];
                }

                $upsertData[] = $row;
            }

            $upsertTMDB->upsert($upsertData);

            // Upsert images
            $upsertImages = app(UpsertTMDBImages::class);
            foreach ($shows as $show) {
                $details = $detailsMap[$show->tvmaze_id] ?? null;
                if ($details && isset($details['images'])) {
                    $upsertImages->upsert($show, $details['images']);
                }
            }

            $this->synced += $shows->count();
            $progress->advance($shows->count());

            if ($limit > 0 && $this->synced >= $limit) {
                return false;
            }
        });

        $progress->finish();
        $this->info("Synced {$this->synced} shows with TMDB.");
    }

    /**
     * @param  Builder<Show>  $query
     */
    private function syncWithDetails(TMDBService $tmdb, UpsertTMDBShowData $upsertTMDB, Builder $query, int $total, string $label, int $limit): void
    {
        $synced = 0;
        $progress = progress(label: $label, steps: $total);
        $progress->start();

        $query->select(['id', 'tvmaze_id', 'imdb_id', 'thetvdb_id', 'tmdb_id', 'name'])->chunkById(self::BATCH_SIZE, function (Collection $shows) use ($tmdb, $upsertTMDB, $progress, $limit, &$synced) {
            if ($limit > 0) {
                $shows = $shows->take($limit - $synced);
            }

            $tmdbIds = $shows->pluck('tmdb_id')->all();
            $now = now()->toDateTimeString();

            $details = $tmdb->showDetailsMany($tmdbIds);

            $upsertData = [];
            foreach ($shows as $show) {
                $showDetails = $details[$show->tmdb_id] ?? null;

                $row = [
                    'tvmaze_id' => $show->tvmaze_id,
                    'name' => $show->name,
                    'tmdb_id' => $show->tmdb_id,
                    'tmdb_synced_at' => $now,
                    'content_ratings' => null,
                    'original_name' => null,
                    'original_language' => null,
                    'thetvdb_id' => $show->thetvdb_id,
                ];

                if ($showDetails) {
                    $row = array_merge($row, UpsertTMDBShowData::mapFromApi($showDetails));
                    // Backfill thetvdb_id from TMDB only if missing locally
                    if ($show->thetvdb_id === null && isset($showDetails['external_ids']['tvdb_id'])) {
                        $row['thetvdb_id'] = $showDetails['external_ids']['tvdb_id'];
                    }
                }

                $upsertData[] = $row;
            }

            $upsertTMDB->upsert($upsertData);

            // Upsert images
            $upsertImages = app(UpsertTMDBImages::class);
            foreach ($shows as $show) {
                $showDetails = $details[$show->tmdb_id] ?? null;
                if ($showDetails && isset($showDetails['images'])) {
                    $upsertImages->upsert($show, $showDetails['images']);
                }
            }

            $count = $shows->count();
            $synced += $count;
            $progress->advance($count);

            if ($limit > 0 && $synced >= $limit) {
                return false;
            }
        });

        $progress->finish();
        $this->info("Updated {$synced} recently changed shows.");
    }
}
