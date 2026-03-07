<?php

namespace App\Actions\TMDB;

use App\Models\Show;

class UpsertTMDBShowData
{
    /**
     * @param  array<int, array{tvmaze_id: int, tmdb_id: ?int, tmdb_synced_at: string, overview: ?string, tagline: ?string, original_name: ?string, original_language: ?string, spoken_languages: ?array, production_companies: ?array, origin_country: ?array, content_ratings: ?array, alternative_titles: ?array, homepage: ?string, in_production: ?bool}>  $shows
     */
    public function upsert(array $shows): int
    {
        $shows = array_map(function (array $show) {
            foreach (['spoken_languages', 'production_companies', 'origin_country', 'content_ratings', 'alternative_titles'] as $field) {
                if (isset($show[$field])) {
                    $show[$field] = json_encode($show[$field]);
                }
            }

            return $show;
        }, $shows);

        return Show::upsert(
            $shows,
            ['tvmaze_id'],
            ['tmdb_id', 'tmdb_synced_at', 'overview', 'tagline', 'original_name', 'original_language', 'spoken_languages', 'production_companies', 'origin_country', 'content_ratings', 'alternative_titles', 'homepage', 'in_production', 'thetvdb_id']
        );
    }

    /**
     * Map TMDB API response data to database columns.
     *
     * @param  array<string, mixed>  $details
     * @return array{tmdb_id: int, overview: ?string, tagline: ?string, original_name: ?string, original_language: ?string, spoken_languages: ?array, production_companies: ?array, origin_country: ?array, content_ratings: ?array, alternative_titles: ?array, homepage: ?string, in_production: ?bool}
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
            'overview' => $details['overview'] ?: null,
            'tagline' => $details['tagline'] ?: null,
            'original_name' => $details['original_name'] ?? null,
            'original_language' => $details['original_language'] ?? null,
            'spoken_languages' => $details['spoken_languages'] ?? null,
            'production_companies' => $details['production_companies'] ?? null,
            'origin_country' => $details['origin_country'] ?? null,
            'content_ratings' => $contentRatings ?: null,
            'alternative_titles' => $details['alternative_titles']['results'] ?? null,
            'homepage' => ($details['homepage'] ?? null) ?: null,
            'in_production' => $details['in_production'] ?? null,
        ];
    }
}
