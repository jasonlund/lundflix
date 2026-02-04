<?php

namespace App\Actions\Tv;

use App\Models\Show;

class UpsertShows
{
    /**
     * @param  array<int, array{tvmaze_id: int, name: string, ...}>  $shows
     */
    public function upsert(array $shows): int
    {
        return Show::upsert(
            $shows,
            ['tvmaze_id'],
            [
                'name', 'type', 'language', 'genres', 'status', 'runtime',
                'premiered', 'ended', 'schedule', 'network', 'web_channel',
                'thetvdb_id',
            ]
        );
    }

    /**
     * Map TVMaze API show data to database columns.
     *
     * @param  array<string, mixed>  $show  Raw show data from TVMaze API
     * @return array<string, mixed>
     */
    public static function mapFromApi(array $show): array
    {
        return [
            'imdb_id' => $show['externals']['imdb'] ?? null,
            'thetvdb_id' => $show['externals']['thetvdb'] ?? null,
            'name' => $show['name'],
            'type' => $show['type'],
            'language' => $show['language'],
            'genres' => json_encode($show['genres']),
            'status' => $show['status'],
            'runtime' => $show['runtime'],
            'premiered' => $show['premiered'],
            'ended' => $show['ended'],
            'schedule' => json_encode($show['schedule']),
            'network' => json_encode($show['network']),
            'web_channel' => json_encode($show['webChannel']),
        ];
    }
}
