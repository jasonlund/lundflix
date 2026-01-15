<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class TVMazeService
{
    private const BASE_URL = 'https://api.tvmaze.com';

    /**
     * Get a paginated list of all shows.
     * Returns null when no more pages exist (404).
     *
     * @return Collection<int, array>|null
     */
    public function shows(int $page = 0): ?Collection
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/shows', ['page' => $page]);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return collect($response->json());
    }

    /**
     * Get all episodes for a show.
     * Returns null if the show doesn't exist (404).
     *
     * @return array<int, array>|null
     */
    public function episodes(int $showId): ?array
    {
        $response = $this->client()
            ->get(self::BASE_URL."/shows/{$showId}/episodes");

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->retry(3, 1000, when: fn ($e, $request) => $e->response?->status() === 429, throw: false);
    }
}
