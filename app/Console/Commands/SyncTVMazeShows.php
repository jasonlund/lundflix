<?php

namespace App\Console\Commands;

use App\Actions\Tv\UpsertShows;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\progress;

class SyncTVMazeShows extends Command
{
    protected $signature = 'tvmaze:sync-shows {--fresh : Start from page 0}';

    protected $description = 'Sync TV shows from TVMaze';

    private const ESTIMATED_PAGES = 360;

    public function handle(TVMazeService $tvmaze, UpsertShows $upsertShows): int
    {
        DB::disableQueryLog();

        $page = $this->option('fresh') ? 0 : $this->calculateStartPage();
        $total = 0;
        $estimatedPages = max(1, self::ESTIMATED_PAGES - $page);

        $progress = progress(label: 'Syncing shows from TVMaze', steps: $estimatedPages);
        $progress->start();

        while (true) {
            try {
                $shows = $tvmaze->shows($page);
            } catch (RequestException $e) {
                if ($e->response->status() === 404) {
                    break; // End of pagination
                }
                throw $e;
            }

            $batch = $shows->map(fn ($show) => [
                'tvmaze_id' => $show['id'],
                ...UpsertShows::mapFromApi($show),
            ])->all();

            $count = count($batch);
            $upsertShows->upsert($batch);

            // Free memory explicitly
            unset($batch, $shows);

            $total += $count;
            $progress
                ->label("Page {$page}")
                ->hint("{$total} shows synced");
            $progress->advance();

            $page++;

            // Force garbage collection every 10 pages
            if ($page % 10 === 0) {
                gc_collect_cycles();
            }
        }

        $progress->finish();

        $this->info("Sync complete. {$total} shows processed.");

        return Command::SUCCESS;
    }

    private function calculateStartPage(): int
    {
        $lastId = Show::max('tvmaze_id') ?? 0;

        return (int) floor($lastId / 250);
    }
}
