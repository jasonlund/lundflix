<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TMDBService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';

    /**
     * Find a TMDB movie by IMDb ID.
     * Returns the first movie result, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function findByImdbId(string $imdbId): ?array
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/find/'.$imdbId, [
                'external_source' => 'imdb_id',
            ]);

        $response->throw();

        $results = $response->json('movie_results', []);

        return $results[0] ?? null;
    }

    /**
     * Get full movie details by TMDB ID.
     * Returns null when the movie is not found (404).
     *
     * @return array<string, mixed>|null
     */
    public function movieDetails(int $tmdbId): ?array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/movie/'.$tmdbId, [
                    'append_to_response' => 'release_dates,alternative_titles',
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            if ($e->response->notFound()) {
                return null;
            }

            throw $e;
        }
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->withToken(config('services.tmdb.api_key'))
            ->timeout(15)
            ->retry(3, 1000, when: fn ($e, $request) => $e instanceof RequestException && $e->response->status() === 429);
    }
}
