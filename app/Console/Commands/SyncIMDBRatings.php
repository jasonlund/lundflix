<?php

namespace App\Console\Commands;

use App\Services\IMDBService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncIMDBRatings extends Command
{
    protected $signature = 'imdb:sync-ratings';

    protected $description = 'Sync vote counts from IMDb ratings dataset for movies and shows';

    public function handle(IMDBService $imdb): int
    {
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

        while (($line = gzgets($handle)) !== false) {
            $fields = explode("\t", trim($line));
            $count++;

            if (count($fields) >= 3) {
                $batch[$fields[0]] = (int) $fields[2]; // imdb_id => num_votes
            }

            if (count($batch) >= $batchSize) {
                $moviesUpdated += $this->updateTable('movies', $batch);
                $showsUpdated += $this->updateTable('shows', $batch);
                $progress->advance($batchSize);
                $progress->hint("{$count} processed");
                $batch = [];
            }
        }

        // Final batch
        if (count($batch) > 0) {
            $moviesUpdated += $this->updateTable('movies', $batch);
            $showsUpdated += $this->updateTable('shows', $batch);
            $progress->advance(count($batch));
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

    private function updateTable(string $table, array $ratings): int
    {
        if (empty($ratings)) {
            return 0;
        }

        $ids = array_keys($ratings);
        $cases = [];
        $bindings = [];

        foreach ($ratings as $imdbId => $numVotes) {
            $cases[] = 'WHEN imdb_id = ? THEN ?';
            $bindings[] = $imdbId;
            $bindings[] = $numVotes;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $caseStatement = implode(' ', $cases);

        $sql = "UPDATE {$table} SET num_votes = CASE {$caseStatement} END WHERE imdb_id IN ({$placeholders})";
        $bindings = array_merge($bindings, $ids);

        return DB::update($sql, $bindings);
    }
}
