<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\IMDB\UpsertIMDBMovies;
use App\Services\IMDBService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncIMDBMovies extends Command
{
    protected $signature = 'imdb:sync-movies';

    protected $description = 'Sync movies from IMDb daily export';

    public function handle(IMDBService $imdb, UpsertIMDBMovies $upsertMovies): int
    {
        // Download export file
        $file = spin(
            fn () => $imdb->downloadExport(),
            'Downloading IMDb export...'
        );

        // Count lines for progress
        $total = spin(
            fn () => $imdb->countExportLines($file),
            'Counting entries...'
        );

        $this->info("Processing {$total} entries from export...");

        // Import with progress bar using batch upserts
        $progress = progress(label: 'Importing movies', steps: $total);
        $progress->start();

        $batch = [];
        $count = 0;
        $processed = 0;
        $upsertBatchSize = 1000;
        $progressBatchSize = 10000;

        foreach ($imdb->parseExportFile($file) as $movie) {
            $processed++;

            if ($movie !== null) {
                $batch[] = $movie;
                $count++;

                if (count($batch) >= $upsertBatchSize) {
                    $upsertMovies->upsert($batch);
                    $batch = [];
                }
            }

            if ($processed % $progressBatchSize === 0) {
                $progress->advance($progressBatchSize);
                $progress->hint("{$count} imported");
            }
        }

        // Final upsert
        if (count($batch) > 0) {
            $upsertMovies->upsert($batch);
        }

        // Final progress advance
        $remaining = $processed % $progressBatchSize;
        if ($remaining > 0) {
            $progress->advance($remaining);
        }

        $progress->finish();
        unlink($file);

        $this->info("Sync complete. {$count} movies imported.");

        return Command::SUCCESS;
    }
}
