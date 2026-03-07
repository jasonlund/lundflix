<?php

namespace App\Actions\TMDB;

use App\Models\Show;

class UpsertTMDBShowData
{
    /**
     * @param  array<int, array{tvmaze_id: int, tmdb_id: ?int, tmdb_synced_at: string, content_ratings: ?array, original_name: ?string, original_language: ?string}>  $shows
     */
    public function upsert(array $shows): int
    {
        $shows = array_map(function (array $show) {
            foreach (['content_ratings'] as $field) {
                if (isset($show[$field])) {
                    $show[$field] = json_encode($show[$field]);
                }
            }

            return $show;
        }, $shows);

        return Show::upsert(
            $shows,
            ['tvmaze_id'],
            ['tmdb_id', 'tmdb_synced_at', 'content_ratings', 'original_name', 'original_language', 'thetvdb_id']
        );
    }

    /**
     * Map TMDB API response data to database columns.
     *
     * @param  array<string, mixed>  $details
     * @return array{tmdb_id: int, content_ratings: ?array, original_name: ?string, original_language: ?string}
     */
    public static function mapFromApi(array $details): array
    {
        $contentRatings = collect($details['content_ratings']['results'] ?? [])
            ->map(fn (array $rating): array => [
                'iso_3166_1' => $rating['iso_3166_1'],
                'rating' => $rating['rating'],
            ])
            ->all();

        return [
            'tmdb_id' => $details['id'],
            'content_ratings' => $contentRatings ?: null,
            'original_name' => $details['original_name'] ?? null,
            'original_language' => $details['original_language'] ?? null,
        ];
    }
}
