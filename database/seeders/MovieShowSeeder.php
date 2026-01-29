<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class MovieShowSeeder extends Seeder
{
    /**
     * Seed movies and shows from compressed SQL dump.
     */
    public function run(): void
    {
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
                'mysql -h%s -P%s -u%s %s < %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($tempSql)
            );

            $result = Process::env($password ? ['MYSQL_PWD' => $password] : [])->run($cmd);

            if (! $result->successful()) {
                throw new \RuntimeException('Failed to import seed data: '.$result->errorOutput());
            }

            $this->command->info('Seeded movies and shows from: '.basename($gzPath));
        } finally {
            if (file_exists($tempSql)) {
                unlink($tempSql);
            }
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
