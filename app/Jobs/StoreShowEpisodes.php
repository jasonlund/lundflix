<?php

namespace App\Jobs;

use App\Actions\Tv\UpsertEpisodes;
use App\Models\Show;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreShowEpisodes implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     */
    public function __construct(
        public Show $show,
        public array $episodes
    ) {}

    public function handle(UpsertEpisodes $upsertEpisodes): void
    {
        $data = array_map(fn ($episode) => [
            'tvmaze_id' => $episode['id'],
            'show_id' => $this->show->id,
            'season' => $episode['season'],
            'number' => $episode['number'],
            'name' => $episode['name'],
            'type' => $episode['type'] ?? 'regular',
            'airdate' => $episode['airdate'] ?? null,
            'airtime' => $episode['airtime'] ?? null,
            'runtime' => $episode['runtime'] ?? null,
            'rating' => $episode['rating'] ?? null,
            'image' => $episode['image'] ?? null,
            'summary' => $episode['summary'] ?? null,
        ], $this->episodes);

        $upsertEpisodes->upsert($data);
    }
}
