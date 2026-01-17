<?php

namespace App\Console\Commands;

use App\Actions\Movie\SyncMovieRatings;
use App\Actions\Tv\SyncShowRatings;
use App\Services\IMDBService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncIMDBRatings extends Command
{
    protected $signature = 'imdb:sync-ratings';

    protected $description = 'Sync vote counts from IMDb ratings dataset for movies and shows';

    public function handle(
        IMDBService $imdb,
        SyncMovieRatings $syncMovieRatings,
        SyncShowRatings $syncShowRatings
    ): int {
        // Download ratings file
        $file = spin(
            fn () => $imdb->downloadRatings(),
            'Downloading IMDb ratings...'
        );

        // Count lines for progress
        $total = spin(
            fn () => $this->countLines($file),
            'Counting entries...'
        );

        $this->info("Processing {$total} ratings...");

        // Process in batches using raw SQL for speed
        $progress = progress(label: 'Updating ratings', steps: $total);
        $progress->start();

        $handle = gzopen($file, 'r');
        gzgets($handle); // Skip header

        $batch = [];
        $count = 0;
        $moviesUpdated = 0;
        $showsUpdated = 0;
        $batchSize = 5000;
        $linesSinceLastAdvance = 0;

        while (($line = gzgets($handle)) !== false) {
            $fields = explode("\t", trim($line));
            $count++;
            $linesSinceLastAdvance++;

            if (count($fields) >= 3) {
                $batch[$fields[0]] = (int) $fields[2]; // imdb_id => num_votes
            }

            if (count($batch) >= $batchSize) {
                $moviesUpdated += $syncMovieRatings->sync($batch);
                $showsUpdated += $syncShowRatings->sync($batch);
                $progress->advance($linesSinceLastAdvance);
                $linesSinceLastAdvance = 0;
                $progress->hint("{$count} processed");
                $batch = [];
            }
        }

        // Final batch
        if (count($batch) > 0) {
            $moviesUpdated += $syncMovieRatings->sync($batch);
            $showsUpdated += $syncShowRatings->sync($batch);
        }
        if ($linesSinceLastAdvance > 0) {
            $progress->advance($linesSinceLastAdvance);
        }

        gzclose($handle);
        $progress->finish();
        unlink($file);

        $this->info("Sync complete. {$moviesUpdated} movies, {$showsUpdated} shows updated.");

        return Command::SUCCESS;
    }

    private function countLines(string $gzipPath): int
    {
        $count = 0;
        $handle = gzopen($gzipPath, 'r');
        gzgets($handle); // Skip header

        while (gzgets($handle) !== false) {
            $count++;
        }

        gzclose($handle);

        return $count;
    }
}
