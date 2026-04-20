<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class MovieShowSeeder extends Seeder
{
    /**
     * Seed movies and shows from compressed SQL dump.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('movies')->truncate();
        DB::table('shows')->truncate();
        DB::table('media')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Truncated movies, shows, and media tables.');

        $gzPath = $this->findLatestSeedFile();
        $tempSql = sys_get_temp_dir().'/seed_'.uniqid().'.sql';

        // Decompress via gunzip to avoid PHP memory limits
        $gunzipResult = Process::run(sprintf('gunzip -c %s > %s', escapeshellarg($gzPath), escapeshellarg($tempSql)));

        if (! $gunzipResult->successful()) {
            throw new \RuntimeException('Failed to decompress seed file: '.$gunzipResult->errorOutput());
        }

        try {
            // Import via mysql CLI
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');

            $cmd = sprintf(
                'mysql --default-character-set=utf8mb4 -h%s -P%s -u%s %s < %s',
                escapeshellarg((string) $host),
                escapeshellarg((string) $port),
                escapeshellarg((string) $username),
                escapeshellarg((string) $database),
                escapeshellarg($tempSql)
            );

            $result = Process::timeout(600)->env($password ? ['MYSQL_PWD' => $password] : [])->run($cmd);

            if (! $result->successful()) {
                throw new \RuntimeException('Failed to import seed data: '.$result->errorOutput());
            }

            $this->command->info('Seeded movies, shows, and media from: '.basename($gzPath));

            $this->repairDoubleEncodedUtf8();
        } finally {
            if (file_exists($tempSql)) {
                unlink($tempSql);
            }
        }
    }

    /**
     * Repair double-encoded UTF-8 in text columns caused by older seed dumps
     * that were imported without --default-character-set=utf8mb4.
     */
    private function repairDoubleEncodedUtf8(): void
    {
        $affected = DB::update('
            UPDATE movies
            SET original_title = CONVERT(BINARY CONVERT(original_title USING latin1) USING utf8mb4)
            WHERE original_title IS NOT NULL
            AND BINARY original_title = BINARY CONVERT(CONVERT(original_title USING latin1) USING utf8mb4)
            AND HEX(original_title) != HEX(CONVERT(BINARY CONVERT(original_title USING latin1) USING utf8mb4))
        ');

        if ($affected > 0) {
            $this->command->info("Repaired double-encoded UTF-8 in {$affected} movie original titles.");
        }
    }

    private function findLatestSeedFile(): string
    {
        $dataDir = database_path('seeders/data');

        // Look for timestamped files first
        $files = File::glob("{$dataDir}/seed_*.sql.gz");

        if (count($files) > 0) {
            // Sort descending to get most recent first (filenames sort chronologically)
            rsort($files);

            return $files[0];
        }

        // Fall back to legacy filename
        $legacyPath = "{$dataDir}/seed.sql.gz";
        if (File::exists($legacyPath)) {
            return $legacyPath;
        }

        throw new \RuntimeException('No seed file found in '.$dataDir);
    }
}
