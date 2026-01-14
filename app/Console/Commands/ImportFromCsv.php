<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFromCsv extends Command
{
    protected $signature = 'app:import-from-csv {--shows-only} {--movies-only}';

    protected $description = 'Import shows and movies from CSV files exported from SQLite';

    public function handle(): int
    {
        $importShows = ! $this->option('movies-only');
        $importMovies = ! $this->option('shows-only');

        if ($importShows) {
            $this->importShows();
        }

        if ($importMovies) {
            $this->importMovies();
        }

        return Command::SUCCESS;
    }

    private function importShows(): void
    {
        $file = storage_path('shows.csv');

        if (! file_exists($file)) {
            $this->error('shows.csv not found in storage/');

            return;
        }

        $this->info('Importing shows...');

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $count = 0;
        $batch = [];
        $batchSize = 200; // Smaller batches due to large text fields

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Show::query()->truncate();

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            $batch[] = [
                'id' => $data['id'],
                'tvmaze_id' => $data['tvmaze_id'],
                'name' => $data['name'],
                'type' => $data['type'] ?: null,
                'language' => $data['language'] ?: null,
                'genres' => $data['genres'] ?: null,
                'status' => $data['status'] ?: null,
                'runtime' => $data['runtime'] ?: null,
                'premiered' => $data['premiered'] ?: null,
                'ended' => $data['ended'] ?: null,
                'official_site' => $data['official_site'] ?: null,
                'schedule' => $data['schedule'] ?: null,
                'rating' => $data['rating'] ?: null,
                'weight' => $data['weight'] ?: null,
                'network' => $data['network'] ?: null,
                'web_channel' => $data['web_channel'] ?: null,
                'externals' => $data['externals'] ?: null,
                'image' => $data['image'] ?: null,
                'summary' => $data['summary'] ?: null,
                'updated_at_tvmaze' => $data['updated_at_tvmaze'] ?: null,
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
            ];

            if (count($batch) >= $batchSize) {
                Show::insert($batch);
                $count += count($batch);
                $this->output->write("\rImported {$count} shows...");
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            Show::insert($batch);
            $count += count($batch);
        }

        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info("Imported {$count} shows.");
    }

    private function importMovies(): void
    {
        $file = storage_path('movies.csv');

        if (! file_exists($file)) {
            $this->error('movies.csv not found in storage/');

            return;
        }

        $this->info('Importing movies...');

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $count = 0;
        $batch = [];
        $batchSize = 1000;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Movie::query()->truncate();

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            $batch[] = [
                'id' => $data['id'],
                'imdb_id' => $data['imdb_id'],
                'title' => $data['title'],
                'year' => $data['year'] ?: null,
                'runtime' => $data['runtime'] ?: null,
                'genres' => $data['genres'] ?: null,
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
            ];

            if (count($batch) >= $batchSize) {
                Movie::insert($batch);
                $count += count($batch);
                $this->output->write("\rImported {$count} movies...");
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            Movie::insert($batch);
            $count += count($batch);
        }

        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info("Imported {$count} movies.");
    }
}
