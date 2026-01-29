<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class ExportSeedData extends Command
{
    protected $signature = 'db:export-seed
                            {--movies=50000 : Number of top movies to export}
                            {--shows=20000 : Number of top shows to export}
                            {--connection=mysql : Database connection to export from}';

    protected $description = 'Export top movies and shows to timestamped seed file';

    private const MOVIE_BATCH_SIZE = 500;

    private const SHOW_BATCH_SIZE = 250;

    /** @var resource|closed-resource|null */
    private $gzHandle = null;

    public function handle(): int
    {
        $movieLimit = (int) $this->option('movies');
        $showLimit = (int) $this->option('shows');
        $connection = $this->option('connection');

        $dataDir = database_path('seeders/data');
        $timestamp = now()->format('Y-m-d');
        $outputPath = "{$dataDir}/seed_{$timestamp}.sql.gz";

        $this->info("Exporting from '{$connection}' connection...");
        $this->info("Movies: top {$movieLimit} by num_votes");
        $this->info("Shows: top {$showLimit} by num_votes");

        // Open gzip file for streaming writes
        $this->gzHandle = gzopen($outputPath, 'wb9');
        if ($this->gzHandle === false) {
            $this->error("Failed to open {$outputPath} for writing");

            return Command::FAILURE;
        }

        $this->write("SET FOREIGN_KEY_CHECKS=0;\n\n");

        // Export movies
        $this->exportMovies($connection, $movieLimit);

        // Export shows
        $this->exportShows($connection, $showLimit);

        // Close the file
        gzclose($this->gzHandle);

        // Delete old seed files
        $this->deleteOldSeedFiles($dataDir, $outputPath);

        $this->newLine();
        $this->info("Exported to: {$outputPath}");
        $this->info(sprintf('File size: %.2f MB', filesize($outputPath) / 1024 / 1024));

        return Command::SUCCESS;
    }

    private function deleteOldSeedFiles(string $dataDir, string $currentFile): void
    {
        // Delete timestamped seed files
        $files = File::glob("{$dataDir}/seed_*.sql.gz");

        foreach ($files as $file) {
            if ($file !== $currentFile) {
                File::delete($file);
                $this->info('Deleted old seed file: '.basename($file));
            }
        }

        // Delete legacy seed.sql.gz if it exists
        $legacyFile = "{$dataDir}/seed.sql.gz";
        if (File::exists($legacyFile)) {
            File::delete($legacyFile);
            $this->info('Deleted legacy seed file: seed.sql.gz');
        }
    }

    private function write(string $data): void
    {
        gzwrite($this->gzHandle, $data);
    }

    private function exportMovies(string $connection, int $limit): void
    {
        $columns = ['id', 'imdb_id', 'title', 'year', 'runtime', 'genres', 'num_votes', 'created_at', 'updated_at'];

        $total = spin(
            fn () => DB::connection($connection)->table('movies')->count(),
            'Counting movies...'
        );

        $this->info("Found {$total} movies in source database");

        $this->write("TRUNCATE TABLE movies;\n");

        $progress = progress(label: 'Exporting movies', steps: min($total, $limit));
        $progress->start();

        $exported = 0;
        $batch = [];

        DB::connection($connection)
            ->table('movies')
            ->orderByDesc('num_votes')
            ->limit($limit)
            ->cursor()
            ->each(function ($row) use (&$batch, &$exported, $columns, $progress) {
                $batch[] = $this->formatMovieRow($row);
                $exported++;

                if (count($batch) >= self::MOVIE_BATCH_SIZE) {
                    $this->write($this->buildInsertStatement('movies', $columns, $batch));
                    $batch = [];
                }

                if ($exported % 1000 === 0) {
                    $progress->advance(1000);
                }
            });

        // Remaining batch
        if (count($batch) > 0) {
            $this->write($this->buildInsertStatement('movies', $columns, $batch));
        }

        $remaining = $exported % 1000;
        if ($remaining > 0) {
            $progress->advance($remaining);
        }

        $progress->finish();
        $this->info("Exported {$exported} movies");

        $this->write("\n");
    }

    private function exportShows(string $connection, int $limit): void
    {
        $columns = [
            'id', 'tvmaze_id', 'imdb_id', 'thetvdb_id', 'name', 'type', 'language', 'genres', 'status',
            'runtime', 'premiered', 'ended', 'schedule', 'num_votes', 'network', 'web_channel',
            'created_at', 'updated_at',
        ];

        $total = spin(
            fn () => DB::connection($connection)->table('shows')->count(),
            'Counting shows...'
        );

        $this->info("Found {$total} shows in source database");

        $this->write("TRUNCATE TABLE shows;\n");

        $progress = progress(label: 'Exporting shows', steps: min($total, $limit));
        $progress->start();

        $exported = 0;
        $batch = [];

        DB::connection($connection)
            ->table('shows')
            ->orderByDesc('num_votes')
            ->limit($limit)
            ->cursor()
            ->each(function ($row) use (&$batch, &$exported, $columns, $progress) {
                $batch[] = $this->formatShowRow($row);
                $exported++;

                if (count($batch) >= self::SHOW_BATCH_SIZE) {
                    $this->write($this->buildInsertStatement('shows', $columns, $batch));
                    $batch = [];
                }

                if ($exported % 500 === 0) {
                    $progress->advance(500);
                }
            });

        // Remaining batch
        if (count($batch) > 0) {
            $this->write($this->buildInsertStatement('shows', $columns, $batch));
        }

        $remaining = $exported % 500;
        if ($remaining > 0) {
            $progress->advance($remaining);
        }

        $progress->finish();
        $this->info("Exported {$exported} shows");

        $this->write("\n");
    }

    /**
     * @return array<string>
     */
    private function formatMovieRow(object $row): array
    {
        return [
            (string) $row->id,
            $this->quote($row->imdb_id),
            $this->quote($row->title),
            $row->year === null ? 'NULL' : (string) $row->year,
            $row->runtime === null ? 'NULL' : (string) $row->runtime,
            $row->genres === null ? 'NULL' : $this->quote($row->genres),
            $row->num_votes === null ? 'NULL' : (string) $row->num_votes,
            $this->quote($row->created_at),
            $this->quote($row->updated_at),
        ];
    }

    /**
     * @return array<string>
     */
    private function formatShowRow(object $row): array
    {
        return [
            (string) $row->id,
            (string) $row->tvmaze_id,
            $row->imdb_id === null ? 'NULL' : $this->quote($row->imdb_id),
            $row->thetvdb_id === null ? 'NULL' : (string) $row->thetvdb_id,
            $this->quote($row->name),
            $row->type === null ? 'NULL' : $this->quote($row->type),
            $row->language === null ? 'NULL' : $this->quote($row->language),
            $row->genres === null ? 'NULL' : $this->quote($row->genres),
            $row->status === null ? 'NULL' : $this->quote($row->status),
            $row->runtime === null ? 'NULL' : (string) $row->runtime,
            $row->premiered === null ? 'NULL' : $this->quote($row->premiered),
            $row->ended === null ? 'NULL' : $this->quote($row->ended),
            $row->schedule === null ? 'NULL' : $this->quote($row->schedule),
            $row->num_votes === null ? 'NULL' : (string) $row->num_votes,
            $row->network === null ? 'NULL' : $this->quote($row->network),
            $row->web_channel === null ? 'NULL' : $this->quote($row->web_channel),
            $this->quote($row->created_at),
            $this->quote($row->updated_at),
        ];
    }

    /**
     * @param  array<string>  $columns
     * @param  array<array<string>>  $rows
     */
    private function buildInsertStatement(string $table, array $columns, array $rows): string
    {
        $columnList = implode(',', $columns);
        $values = array_map(fn ($row) => '('.implode(',', $row).')', $rows);

        return "INSERT INTO {$table} ({$columnList}) VALUES \n".implode(",\n", $values).";\n";
    }

    private function quote(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        // Escape special characters for MySQL
        $escaped = str_replace(
            ['\\', "\x00", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            $value
        );

        return "'{$escaped}'";
    }
}
