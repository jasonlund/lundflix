<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Services\TMDBService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class SyncTMDBMovies extends Command
{
    protected $signature = 'tmdb:sync-movies
        {--date= : Export date in MM_DD_YYYY format}
        {--min-popularity=1 : Minimum popularity score to import (the Rise of Taj line)}';

    protected $description = 'Sync movies from TMDB daily export';

    public function handle(TMDBService $tmdb): int
    {
        $date = $this->option('date');
        $minPopularity = (float) $this->option('min-popularity');

        $this->info("Filtering movies with popularity >= {$minPopularity}");

        // Download export file
        $file = spin(
            fn () => $tmdb->downloadMovieExport($date),
            'Downloading TMDB export...'
        );

        // Count lines for progress
        $total = spin(
            fn () => $tmdb->countExportLines($file),
            'Counting movies...'
        );

        $this->info("Processing {$total} movies from export...");

        // Import with progress bar
        $progress = progress(label: 'Importing movies', steps: $total);
        $progress->start();

        $count = 0;
        foreach ($tmdb->parseExportFile($file, $minPopularity) as $movie) {
            Movie::updateOrCreate(
                ['tmdb_id' => $movie['id']],
                [
                    'title' => $movie['original_title'],
                    'popularity' => $movie['popularity'] ?? 0,
                    'video' => $movie['video'] ?? false,
                ]
            );

            $count++;

            if ($count % 1000 === 0) {
                $progress
                    ->label('Importing movies')
                    ->hint("{$count} imported");
            }

            $progress->advance();
        }

        $progress->finish();
        unlink($file);

        $this->info("Sync complete. {$count} movies imported (filtered from {$total} total).");

        return Command::SUCCESS;
    }
}
