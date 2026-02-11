<?php

namespace App\Actions\Movie;

use App\Enums\TMDBReleaseType;
use App\Models\Movie;

class UpsertTMDBData
{
    /**
     * @param  array<int, array{imdb_id: string, tmdb_id: ?int, release_date: ?string, digital_release_date: ?string, production_companies: ?array, spoken_languages: ?array, alternative_titles: ?array, original_language: ?string, tmdb_synced_at: string}>  $movies
     */
    public function upsert(array $movies): int
    {
        $movies = array_map(function (array $movie) {
            foreach (['production_companies', 'spoken_languages', 'alternative_titles'] as $field) {
                if (isset($movie[$field])) {
                    $movie[$field] = json_encode($movie[$field]);
                }
            }

            return $movie;
        }, $movies);

        return Movie::upsert(
            $movies,
            ['imdb_id'],
            ['tmdb_id', 'release_date', 'digital_release_date', 'production_companies', 'spoken_languages', 'alternative_titles', 'original_language', 'tmdb_synced_at']
        );
    }

    /**
     * Map TMDB API response data to database columns.
     *
     * @param  array<string, mixed>  $details  Full TMDB movie details response
     * @return array{tmdb_id: int, release_date: ?string, digital_release_date: ?string, production_companies: ?array, spoken_languages: ?array, alternative_titles: ?array, original_language: ?string}
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
        ];
    }
}
