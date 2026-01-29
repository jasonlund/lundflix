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
}
