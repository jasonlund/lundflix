<?php

namespace App\Actions\Tv;

use App\Models\Episode;

class UpsertEpisodes
{
    /**
     * @param  array<int, array{tvmaze_id: int, show_id: int, season: int, number: int, name: string, rating?: mixed, image?: mixed, ...}>  $episodes
     */
    public function upsert(array $episodes): int
    {
        // Encode array fields for upsert (model casts don't apply)
        $data = array_map(fn ($ep) => [
            ...$ep,
            'rating' => is_array($ep['rating'] ?? null) ? json_encode($ep['rating']) : $ep['rating'],
            'image' => is_array($ep['image'] ?? null) ? json_encode($ep['image']) : $ep['image'],
        ], $episodes);

        return Episode::upsert(
            $data,
            ['tvmaze_id'],
            ['show_id', 'season', 'number', 'name', 'type', 'airdate', 'airtime', 'runtime', 'rating', 'image', 'summary']
        );
    }
}
