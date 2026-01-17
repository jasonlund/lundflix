<?php

namespace App\Console\Commands;

use App\Actions\Tv\UpsertShows;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class SyncTVMazeShows extends Command
{
    protected $signature = 'tvmaze:sync-shows {--fresh : Start from page 0}';

    protected $description = 'Sync TV shows from TVMaze';

    private const ESTIMATED_PAGES = 360;

    public function handle(TVMazeService $tvmaze, UpsertShows $upsertShows): int
    {
        $page = $this->option('fresh') ? 0 : $this->calculateStartPage();
        $total = 0;
        $estimatedPages = max(1, self::ESTIMATED_PAGES - $page);

        $progress = progress(label: 'Syncing shows from TVMaze', steps: $estimatedPages);
        $progress->start();

        while (($shows = $tvmaze->shows($page)) !== null) {
            $batch = $shows->map(fn ($show) => [
                'tvmaze_id' => $show['id'],
                ...$this->mapShowData($show),
            ])->all();

            $upsertShows->upsert($batch);

            $total += $shows->count();
            $progress
                ->label("Page {$page}")
                ->hint("{$total} shows synced");
            $progress->advance();

            $page++;
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

    private function mapShowData(array $show): array
    {
        return [
            'imdb_id' => $show['externals']['imdb'] ?? null,
            'name' => $show['name'],
            'type' => $show['type'],
            'language' => $show['language'],
            'genres' => json_encode($show['genres']),
            'status' => $show['status'],
            'runtime' => $show['runtime'],
            'premiered' => $show['premiered'],
            'ended' => $show['ended'],
            'official_site' => $show['officialSite'],
            'schedule' => json_encode($show['schedule']),
            'rating' => json_encode($show['rating']),
            'weight' => $show['weight'],
            'network' => json_encode($show['network']),
            'web_channel' => json_encode($show['webChannel']),
            'externals' => json_encode($show['externals']),
            'image' => json_encode($show['image']),
            'summary' => $show['summary'],
            'updated_at_tvmaze' => $show['updated'],
        ];
    }
}
