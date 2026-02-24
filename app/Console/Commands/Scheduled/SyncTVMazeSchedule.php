<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\TVMaze\UpsertTVMazeEpisodes;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncTVMazeSchedule extends Command
{
    protected $signature = 'tvmaze:sync-schedule';

    protected $description = 'Sync upcoming episodes from TVMaze full schedule for shows with existing episodes';

    public function handle(TVMazeService $tvmaze, UpsertTVMazeEpisodes $upsertEpisodes): int
    {
        DB::disableQueryLog();

        // Get tvmaze_ids of shows that have episodes (user has viewed them)
        $trackedShowIds = Show::query()
            ->whereHas('episodes')
            ->pluck('tvmaze_id', 'id')
            ->all();

        if (empty($trackedShowIds)) {
            $this->info('No shows with episodes to sync.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Syncing schedule for %d tracked shows...', count($trackedShowIds)));

        // Fetch full schedule (large response ~10MB)
        $schedule = spin(
            fn () => $tvmaze->fullSchedule(),
            'Fetching full schedule from TVMaze...'
        );

        $this->info(sprintf('Processing %d episodes from schedule...', count($schedule)));

        // Create lookup of tvmaze_id => show_id for fast filtering
        $tvmazeToShowId = array_flip($trackedShowIds);

        // Filter and group episodes by show
        $episodesByShow = [];
        foreach ($schedule as $episode) {
            $showTvmazeId = $episode['_embedded']['show']['id'] ?? null;

            if ($showTvmazeId === null || ! isset($tvmazeToShowId[$showTvmazeId])) {
                continue;
            }

            $showId = $tvmazeToShowId[$showTvmazeId];
            $episodesByShow[$showId][] = $episode;
        }

        // Free the large schedule array
        unset($schedule);
        gc_collect_cycles();

        if (empty($episodesByShow)) {
            $this->info('No relevant episodes found in schedule.');

            return Command::SUCCESS;
        }

        $totalShows = count($episodesByShow);
        $totalEpisodes = array_sum(array_map('count', $episodesByShow));

        $this->info(sprintf('Found %d episodes across %d tracked shows.', $totalEpisodes, $totalShows));

        // Process in batches
        $progress = progress(label: 'Upserting episodes', steps: $totalShows);
        $progress->start();

        $upsertedCount = 0;
        $batchSize = 1000;
        $batch = [];
        $showsProcessed = 0;

        foreach ($episodesByShow as $showId => $episodes) {
            foreach ($episodes as $episode) {
                $batch[] = [
                    'tvmaze_id' => $episode['id'],
                    'show_id' => $showId,
                    'season' => $episode['season'],
                    'number' => $episode['number'],
                    'name' => $episode['name'],
                    'type' => $episode['type'] ?? 'regular',
                    'airdate' => $episode['airdate'] ?? null,
                    'airtime' => $episode['airtime'] ?? null,
                    'runtime' => $episode['runtime'] ?? null,
                    'rating' => $episode['rating'] ?? null,
                    'image' => $episode['image'] ?? null,
                    'summary' => $episode['summary'] ?? null,
                ];

                if (count($batch) >= $batchSize) {
                    $upsertedCount += $upsertEpisodes->upsert($batch);
                    $batch = [];
                    gc_collect_cycles();
                }
            }

            $showsProcessed++;
            $progress->advance();
            $progress->hint("{$showsProcessed}/{$totalShows} shows");

            // Free memory after each show
            unset($episodesByShow[$showId]);
        }

        // Final batch
        if (! empty($batch)) {
            $upsertedCount += $upsertEpisodes->upsert($batch);
        }

        $progress->finish();

        $this->info("Sync complete. {$upsertedCount} episodes upserted.");

        return Command::SUCCESS;
    }
}
