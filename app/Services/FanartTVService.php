<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
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
        $response = $this->client()
            ->get(self::BASE_URL.'/movies/'.$imdbId);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /**
     * Get artwork for a TV show by TVDB ID.
     * Returns null when the show has no artwork (404).
     *
     * @return array<string, mixed>|null
     */
    public function show(int $tvdbId): ?array
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/tv/'.$tvdbId);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->withHeaders([
                'api-key' => config('services.fanart.api_key'),
            ])
            ->retry(3, 1000, when: fn ($e, $request) => $e->response?->status() === 429, throw: false);
    }
}
