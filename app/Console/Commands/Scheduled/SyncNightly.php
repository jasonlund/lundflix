<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncNightly extends Command
{
    protected $signature = 'sync:nightly';

    protected $description = 'Run all nightly sync commands in sequence';

    /** @var string[] */
    private const COMMANDS = [
        'tvmaze:sync-shows',
        'tvmaze:sync-updates',
        'imdb:sync-movies',
        'imdb:sync-ratings',
        'tmdb:sync-movies',
        'tmdb:sync-shows',
        'tvmaze:sync-schedule',
    ];

    public function handle(): int
    {
        $hasFailures = false;

        foreach (self::COMMANDS as $command) {
            try {
                $this->info("Running {$command}...");
                $exitCode = Artisan::call($command, [], $this->output);

                if ($exitCode !== Command::SUCCESS) {
                    $hasFailures = true;
                    $this->error("{$command} failed with exit code {$exitCode}.");
                }
            } catch (\Throwable $e) {
                $hasFailures = true;
                $this->error("{$command} failed: {$e->getMessage()}");
                report($e);
            }
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
