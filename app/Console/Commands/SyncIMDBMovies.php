<?php

namespace App\Console\Commands;

use App\Actions\Movie\UpsertMovies;
use App\Services\IMDBService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncIMDBMovies extends Command
{
    protected $signature = 'imdb:sync-movies';

    protected $description = 'Sync movies from IMDb daily export';

    public function handle(IMDBService $imdb, UpsertMovies $upsertMovies): int
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
        $batchSize = 1000;

        foreach ($imdb->parseExportFile($file) as $movie) {
            $batch[] = $movie;
            $count++;

            if (count($batch) >= $batchSize) {
                $upsertMovies->upsert($batch);
                $progress->advance($batchSize);
                $progress->hint("{$count} imported");
                $batch = [];
            }
        }

        // Final batch
        if (count($batch) > 0) {
            $upsertMovies->upsert($batch);
            $progress->advance(count($batch));
        }

        $progress->finish();
        unlink($file);

        $this->info("Sync complete. {$count} movies imported.");

        return Command::SUCCESS;
    }
}
