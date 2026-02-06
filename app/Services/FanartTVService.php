<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FanartTVService
{
    private const BASE_URL = 'https://webservice.fanart.tv/v3';

    /**
     * Get artwork for a movie by IMDB ID.
     * Returns null when the movie has no artwork (404).
     *
     * @return array<string, mixed>|null
     */
    public function movie(string $imdbId): ?array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/movies/'.$imdbId);

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
     * Get artwork for a TV show by TVDB ID.
     * Returns null when the show has no artwork (404).
     *
     * @return array<string, mixed>|null
     */
    public function show(int $tvdbId): ?array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/tv/'.$tvdbId);

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
     * Get recently updated movies from fanart.tv.
     * Returns a list of IMDB IDs that have new/updated artwork.
     *
     * @param  int|null  $since  Unix timestamp to get updates since (null = last 2-3 days)
     * @return array<int, string>
     */
    public function latestMovies(?int $since = null): array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/movies/latest', $since ? ['date' => $since] : []);

            $response->throw();

            return collect($response->json())
                ->pluck('imdb_id')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Get recently updated TV shows from fanart.tv.
     * Returns a list of TVDB IDs that have new/updated artwork.
     *
     * @param  int|null  $since  Unix timestamp to get updates since (null = last 2-3 days)
     * @return array<int, int>
     */
    public function latestShows(?int $since = null): array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/tv/latest', $since ? ['date' => $since] : []);

            $response->throw();

            return collect($response->json())
                ->pluck('thetvdb_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Find the best image from a list of images.
     * Prefers English or language-neutral images with the most likes.
     *
     * @param  array<int, array<string, mixed>>  $images
     * @return array<string, mixed>|null
     */
    public function bestImage(array $images): ?array
    {
        return collect($images)
            ->filter(fn ($img) => in_array($img['lang'] ?? null, ['en', null, '']))
            ->sortByDesc(fn ($img) => (int) ($img['likes'] ?? 0))
            ->first();
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->withHeaders([
                'api-key' => config('services.fanart.api_key'),
            ])
            ->timeout(15)
            ->retry(3, 1000, when: fn ($e, $request) => $e instanceof RequestException && $e->response->status() === 429);
    }
}
