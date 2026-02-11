<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TMDBService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';

    private const POOL_MAX_RETRIES = 2;

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
     * Find TMDB movies for multiple IMDb IDs concurrently.
     * Returns an associative array keyed by IMDb ID, with the first movie result or null.
     *
     * @param  array<int, string>  $imdbIds
     * @return array<string, array<string, mixed>|null>
     */
    public function findManyByImdbId(array $imdbIds): array
    {
        $results = [];
        $pending = collect($imdbIds);

        for ($attempt = 0; $attempt <= self::POOL_MAX_RETRIES && $pending->isNotEmpty(); $attempt++) {
            if ($attempt > 0) {
                sleep(1);
            }

            $responses = Http::pool(fn (Pool $pool) => $pending->map(
                fn (string $imdbId) => $this->baseRequest($pool->as($imdbId))
                    ->get(self::BASE_URL.'/find/'.$imdbId, [
                        'external_source' => 'imdb_id',
                    ]),
            )->all(), concurrency: 20);

            $pending->each(function (string $imdbId) use ($responses, &$results, $attempt) {
                $response = $responses[$imdbId];

                if ($response instanceof Response && $response->successful()) {
                    $results[$imdbId] = $response->json('movie_results', [])[0] ?? null;
                } elseif ($response instanceof Response && $response->notFound()) {
                    $results[$imdbId] = null;
                } elseif ($attempt >= self::POOL_MAX_RETRIES) {
                    $results[$imdbId] = null;
                }
            });

            $pending = $pending->reject(fn (string $imdbId) => array_key_exists($imdbId, $results))->values();
        }

        return $results;
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

    /**
     * Get full movie details for multiple TMDB IDs concurrently.
     * Returns an associative array keyed by TMDB ID, with details or null for 404s.
     *
     * @param  array<int, int>  $tmdbIds
     * @return array<int, array<string, mixed>|null>
     */
    public function movieDetailsMany(array $tmdbIds): array
    {
        $results = [];
        $pending = collect($tmdbIds);

        for ($attempt = 0; $attempt <= self::POOL_MAX_RETRIES && $pending->isNotEmpty(); $attempt++) {
            if ($attempt > 0) {
                sleep(1);
            }

            $responses = Http::pool(fn (Pool $pool) => $pending->map(
                fn (int $tmdbId) => $this->baseRequest($pool->as((string) $tmdbId))
                    ->get(self::BASE_URL.'/movie/'.$tmdbId, [
                        'append_to_response' => 'release_dates,alternative_titles',
                    ]),
            )->all(), concurrency: 20);

            $pending->each(function (int $tmdbId) use ($responses, &$results, $attempt) {
                $response = $responses[(string) $tmdbId];

                if ($response instanceof Response && $response->successful()) {
                    $results[$tmdbId] = $response->json();
                } elseif ($response instanceof Response && $response->notFound()) {
                    $results[$tmdbId] = null;
                } elseif ($attempt >= self::POOL_MAX_RETRIES) {
                    $results[$tmdbId] = null;
                }
            });

            $pending = $pending->reject(fn (int $tmdbId) => array_key_exists($tmdbId, $results))->values();
        }

        return $results;
    }

    /**
     * Get IDs of movies that have changed on TMDB within the given date range.
     * Defaults to the last 14 days (TMDB maximum).
     *
     * @return array<int, int>
     */
    public function changedMovieIds(?string $startDate = null, ?string $endDate = null): array
    {
        $ids = collect();
        $page = 1;

        $params = array_filter([
            'start_date' => $startDate ?? now()->subDays(14)->format('Y-m-d'),
            'end_date' => $endDate,
        ]);

        do {
            $response = $this->client()
                ->get(self::BASE_URL.'/movie/changes', [
                    ...$params,
                    'page' => $page,
                ]);

            $response->throw();

            $data = $response->json();

            $ids = $ids->merge(collect($data['results'] ?? [])->pluck('id'));

            $totalPages = $data['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $ids->unique()->values()->all();
    }

    private function baseRequest(PendingRequest $request): PendingRequest
    {
        return $request
            ->accept('application/json')
            ->withToken(config('services.tmdb.api_key'))
            ->timeout(15);
    }

    private function client(): PendingRequest
    {
        return $this->baseRequest(Http::createPendingRequest())
            ->retry(3, 1000, when: fn ($e, $request) => $e instanceof RequestException && $e->response->status() === 429);
    }
}
