<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class SyncTVMazeShows extends Command
{
    protected $signature = 'tvmaze:sync-shows {--fresh : Start from page 0}';

    protected $description = 'Sync TV shows from TVMaze';

    private const ESTIMATED_PAGES = 360;

    public function handle(TVMazeService $tvmaze): int
    {
        $page = $this->option('fresh') ? 0 : $this->calculateStartPage();
        $total = 0;
        $estimatedPages = max(1, self::ESTIMATED_PAGES - $page);

        $progress = progress(label: 'Syncing shows from TVMaze', steps: $estimatedPages);
        $progress->start();

        while (($shows = $tvmaze->shows($page)) !== null) {
            foreach ($shows as $show) {
                Show::updateOrCreate(
                    ['tvmaze_id' => $show['id']],
                    $this->mapShowData($show)
                );
            }

            $total += $shows->count();
            $progress
                ->label("Page {$page}")
                ->hint("{$total} shows synced");
            $progress->advance();

            $page++;
            usleep(500000); // 0.5s delay for rate limiting
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
            'name' => $show['name'],
            'type' => $show['type'],
            'language' => $show['language'],
            'genres' => $show['genres'],
            'status' => $show['status'],
            'runtime' => $show['runtime'],
            'premiered' => $show['premiered'],
            'ended' => $show['ended'],
            'official_site' => $show['officialSite'],
            'schedule' => $show['schedule'],
            'rating' => $show['rating'],
            'weight' => $show['weight'],
            'network' => $show['network'],
            'web_channel' => $show['webChannel'],
            'externals' => $show['externals'],
            'image' => $show['image'],
            'summary' => $show['summary'],
            'updated_at_tvmaze' => $show['updated'],
        ];
    }
}
