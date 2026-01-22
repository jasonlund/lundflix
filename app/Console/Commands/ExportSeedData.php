<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class ExportSeedData extends Command
{
    protected $signature = 'db:export-seed
                            {--movies=50000 : Number of top movies to export}
                            {--shows=20000 : Number of top shows to export}
                            {--connection=lundflix : Database connection to export from}';

    protected $description = 'Export top movies and shows to seed.sql.gz';

    private const MOVIE_BATCH_SIZE = 500;

    private const SHOW_BATCH_SIZE = 250;

    public function handle(): int
    {
        $movieLimit = (int) $this->option('movies');
        $showLimit = (int) $this->option('shows');
        $connection = $this->option('connection');

        $outputPath = database_path('seeders/data/seed.sql.gz');

        $this->info("Exporting from '{$connection}' connection...");
        $this->info("Movies: top {$movieLimit} by num_votes");
        $this->info("Shows: top {$showLimit} by num_votes");

        $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Export movies
        $sql .= $this->exportMovies($connection, $movieLimit);

        // Export shows
        $sql .= $this->exportShows($connection, $showLimit);

        // Compress and save
        spin(function () use ($sql, $outputPath) {
            $compressed = gzencode($sql, 9);
            file_put_contents($outputPath, $compressed);
        }, 'Compressing and saving...');

        $this->newLine();
        $this->info("Exported to: {$outputPath}");
        $this->info(sprintf('File size: %.2f MB', filesize($outputPath) / 1024 / 1024));

        return Command::SUCCESS;
    }

    private function exportMovies(string $connection, int $limit): string
    {
        $columns = ['id', 'imdb_id', 'title', 'year', 'runtime', 'genres', 'num_votes', 'created_at', 'updated_at'];

        $total = spin(
            fn () => DB::connection($connection)->table('movies')->count(),
            'Counting movies...'
        );

        $this->info("Found {$total} movies in source database");

        $sql = "TRUNCATE TABLE movies;\n";

        $progress = progress(label: 'Exporting movies', steps: min($total, $limit));
        $progress->start();

        $exported = 0;
        $batch = [];

        DB::connection($connection)
            ->table('movies')
            ->orderByDesc('num_votes')
            ->limit($limit)
            ->cursor()
            ->each(function ($row) use (&$sql, &$batch, &$exported, $columns, $progress) {
                $batch[] = $this->formatMovieRow($row, $columns);
                $exported++;

                if (count($batch) >= self::MOVIE_BATCH_SIZE) {
                    $sql .= $this->buildInsertStatement('movies', $columns, $batch);
                    $batch = [];
                }

                if ($exported % 1000 === 0) {
                    $progress->advance(1000);
                }
            });

        // Remaining batch
        if (count($batch) > 0) {
            $sql .= $this->buildInsertStatement('movies', $columns, $batch);
        }

        $remaining = $exported % 1000;
        if ($remaining > 0) {
            $progress->advance($remaining);
        }

        $progress->finish();
        $this->info("Exported {$exported} movies");

        return $sql."\n";
    }

    private function exportShows(string $connection, int $limit): string
    {
        $columns = [
            'id', 'tvmaze_id', 'imdb_id', 'name', 'type', 'language', 'genres', 'status',
            'runtime', 'premiered', 'ended', 'official_site', 'schedule', 'rating', 'weight',
            'num_votes', 'network', 'web_channel', 'externals', 'image', 'summary',
            'updated_at_tvmaze', 'created_at', 'updated_at',
        ];

        $total = spin(
            fn () => DB::connection($connection)->table('shows')->count(),
            'Counting shows...'
        );

        $this->info("Found {$total} shows in source database");

        $sql = "TRUNCATE TABLE shows;\n";

        $progress = progress(label: 'Exporting shows', steps: min($total, $limit));
        $progress->start();

        $exported = 0;
        $batch = [];

        DB::connection($connection)
            ->table('shows')
            ->orderByDesc('num_votes')
            ->limit($limit)
            ->cursor()
            ->each(function ($row) use (&$sql, &$batch, &$exported, $columns, $progress) {
                $batch[] = $this->formatShowRow($row, $columns);
                $exported++;

                if (count($batch) >= self::SHOW_BATCH_SIZE) {
                    $sql .= $this->buildInsertStatement('shows', $columns, $batch);
                    $batch = [];
                }

                if ($exported % 500 === 0) {
                    $progress->advance(500);
                }
            });

        // Remaining batch
        if (count($batch) > 0) {
            $sql .= $this->buildInsertStatement('shows', $columns, $batch);
        }

        $remaining = $exported % 500;
        if ($remaining > 0) {
            $progress->advance($remaining);
        }

        $progress->finish();
        $this->info("Exported {$exported} shows");

        return $sql."\n";
    }

    /**
     * @param  array<string>  $columns
     * @return array<string>
     */
    private function formatMovieRow(object $row, array $columns): array
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
     * @param  array<string>  $columns
     * @return array<string>
     */
    private function formatShowRow(object $row, array $columns): array
    {
        return [
            (string) $row->id,
            $this->quote($row->tvmaze_id),
            $row->imdb_id === null ? 'NULL' : $this->quote($row->imdb_id),
            $this->quote($row->name),
            $row->type === null ? 'NULL' : $this->quote($row->type),
            $row->language === null ? 'NULL' : $this->quote($row->language),
            $row->genres === null ? 'NULL' : $this->quote($row->genres),
            $row->status === null ? 'NULL' : $this->quote($row->status),
            $row->runtime === null ? 'NULL' : (string) $row->runtime,
            $row->premiered === null ? 'NULL' : $this->quote($row->premiered),
            $row->ended === null ? 'NULL' : $this->quote($row->ended),
            $row->official_site === null ? 'NULL' : $this->quote($row->official_site),
            $row->schedule === null ? 'NULL' : $this->quote($row->schedule),
            $row->rating === null ? 'NULL' : $this->quote($row->rating),
            $row->weight === null ? 'NULL' : (string) $row->weight,
            $row->num_votes === null ? 'NULL' : (string) $row->num_votes,
            $row->network === null ? 'NULL' : $this->quote($row->network),
            $row->web_channel === null ? 'NULL' : $this->quote($row->web_channel),
            $row->externals === null ? 'NULL' : $this->quote($row->externals),
            $row->image === null ? 'NULL' : $this->quote($row->image),
            $row->summary === null ? 'NULL' : $this->quote($row->summary),
            $row->updated_at_tvmaze === null ? 'NULL' : (string) $row->updated_at_tvmaze,
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
