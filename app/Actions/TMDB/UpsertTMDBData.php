<?php

namespace App\Actions\TMDB;

use App\Enums\TMDBReleaseType;
use App\Models\Movie;

class UpsertTMDBData
{
    /**
     * @param  array<int, array{imdb_id: string, tmdb_id: ?int, release_date: ?string, digital_release_date: ?string, production_companies: ?array, spoken_languages: ?array, alternative_titles: ?array, original_language: ?string, original_title: ?string, tagline: ?string, status: ?string, budget: ?int, revenue: ?int, origin_country: ?array, release_dates: ?array, tmdb_synced_at: string}>  $movies
     */
    public function upsert(array $movies): int
    {
        $movies = array_map(function (array $movie) {
            foreach (['production_companies', 'spoken_languages', 'alternative_titles', 'origin_country', 'release_dates'] as $field) {
                if (isset($movie[$field])) {
                    $movie[$field] = json_encode($movie[$field]);
                }
            }

            return $movie;
        }, $movies);

        return Movie::upsert(
            $movies,
            ['imdb_id'],
            ['tmdb_id', 'release_date', 'digital_release_date', 'production_companies', 'spoken_languages', 'alternative_titles', 'original_language', 'original_title', 'tagline', 'status', 'budget', 'revenue', 'origin_country', 'release_dates', 'tmdb_synced_at']
        );
    }

    /**
     * Map TMDB API response data to database columns.
     *
     * @param  array<string, mixed>  $details  Full TMDB movie details response
     * @return array{tmdb_id: int, release_date: ?string, digital_release_date: ?string, production_companies: ?array, spoken_languages: ?array, alternative_titles: ?array, original_language: ?string, original_title: ?string, tagline: ?string, status: ?string, budget: ?int, revenue: ?int, origin_country: ?array, release_dates: ?array}
     */
    public static function mapFromApi(array $details): array
    {
        $usReleases = collect($details['release_dates']['results'] ?? [])
            ->firstWhere('iso_3166_1', 'US');
        $digitalRelease = collect($usReleases['release_dates'] ?? [])
            ->firstWhere('type', TMDBReleaseType::Digital->value);

        return [
            'tmdb_id' => $details['id'],
            'release_date' => $details['release_date'] ?: null,
            'digital_release_date' => $digitalRelease ? substr($digitalRelease['release_date'], 0, 10) : null,
            'production_companies' => $details['production_companies'] ?? null,
            'spoken_languages' => $details['spoken_languages'] ?? null,
            'alternative_titles' => $details['alternative_titles']['titles'] ?? null,
            'original_language' => $details['original_language'] ?? null,
            'original_title' => $details['original_title'] ?? null,
            'tagline' => $details['tagline'] ?: null,
            'status' => $details['status'] ?? null,
            'budget' => ($details['budget'] ?? 0) > 0 ? $details['budget'] : null,
            'revenue' => ($details['revenue'] ?? 0) > 0 ? $details['revenue'] : null,
            'origin_country' => $details['origin_country'] ?? null,
            'release_dates' => $details['release_dates']['results'] ?? null,
        ];
    }
}
