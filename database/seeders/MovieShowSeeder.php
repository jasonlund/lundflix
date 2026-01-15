<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Process;

class MovieShowSeeder extends Seeder
{
    /**
     * Seed movies and shows from compressed SQL dump.
     */
    public function run(): void
    {
        $gzPath = database_path('seeders/data/seed.sql.gz');
        $tempSql = sys_get_temp_dir().'/seed_'.uniqid().'.sql';

        // Decompress
        file_put_contents($tempSql, gzdecode(file_get_contents($gzPath)));

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

        unlink($tempSql);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to import seed data: '.$result->errorOutput());
        }

        $this->command->info('Seeded 50,000 movies and 20,000 shows.');
    }
}
