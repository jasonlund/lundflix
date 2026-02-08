<?php

namespace App\Jobs;

use App\Enums\TMDBReleaseType;
use App\Models\Movie;
use App\Services\TMDBService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreTMDBData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Movie $movie,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->movie->id;
    }

    public function handle(TMDBService $tmdb): void
    {
        $findResult = $tmdb->findByImdbId($this->movie->imdb_id);

        if (! $findResult) {
            $this->movie->update(['tmdb_synced_at' => now()]);

            return;
        }

        $tmdbId = $findResult['id'];

        $details = $tmdb->movieDetails($tmdbId);

        if (! $details) {
            $this->movie->update([
                'tmdb_id' => $tmdbId,
                'tmdb_synced_at' => now(),
            ]);

            return;
        }

        $usReleases = collect($details['release_dates']['results'] ?? [])
            ->firstWhere('iso_3166_1', 'US');
        $digitalRelease = collect($usReleases['release_dates'] ?? [])
            ->firstWhere('type', TMDBReleaseType::Digital->value);

        $this->movie->update([
            'tmdb_id' => $tmdbId,
            'release_date' => $details['release_date'] ?: null,
            'digital_release_date' => $digitalRelease ? substr($digitalRelease['release_date'], 0, 10) : null,
            'production_companies' => $details['production_companies'] ?? null,
            'spoken_languages' => $details['spoken_languages'] ?? null,
            'alternative_titles' => $details['alternative_titles']['titles'] ?? null,
            'original_language' => $details['original_language'] ?? null,
            'tmdb_synced_at' => now(),
        ]);
    }
}
