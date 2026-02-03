<?php

namespace App\Console\Commands;

use App\Actions\Tv\UpsertShows;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\progress;

class SyncTVMazeUpdates extends Command
{
    protected $signature = 'tvmaze:sync-updates {--since=day : Time period (day, week, month)}';

    protected $description = 'Sync recently updated TV shows from TVMaze';

    public function handle(TVMazeService $tvmaze, UpsertShows $upsertShows): int
    {
        $since = $this->option('since');

        $this->info("Fetching show updates from TVMaze (since: {$since})...");

        $updates = $tvmaze->showUpdates($since);
        $updatedIds = array_keys($updates);
        $this->info(sprintf('Found %d updated shows in TVMaze.', count($updatedIds)));

        // Filter to only shows we have in our database
        $existingIds = Show::whereIn('tvmaze_id', $updatedIds)
            ->pluck('tvmaze_id')
            ->all();

        if (empty($existingIds)) {
            $this->info('No tracked shows need updating.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Updating %d tracked shows...', count($existingIds)));

        $progress = progress(label: 'Fetching show data', steps: count($existingIds));
        $progress->start();

        $batch = [];
        $failed = 0;

        foreach ($existingIds as $tvmazeId) {
            try {
                $show = $tvmaze->show($tvmazeId);

                $batch[] = [
                    'tvmaze_id' => $show['id'],
                    ...UpsertShows::mapFromApi($show),
                ];

                // Process in batches of 100
                if (count($batch) >= 100) {
                    $upsertShows->upsert($batch);
                    $batch = [];
                }
            } catch (RequestException) {
                $failed++;
            }

            $progress->advance();
        }

        // Process remaining batch
        if (! empty($batch)) {
            $upsertShows->upsert($batch);
        }

        $progress->finish();

        $updated = count($existingIds) - $failed;
        $this->info("Sync complete. {$updated} shows updated.");

        if ($failed > 0) {
            $this->warn("{$failed} shows could not be fetched.");
        }

        return Command::SUCCESS;
    }
}
