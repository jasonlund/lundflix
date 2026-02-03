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
     *
     * @return Collection<int, array>
     */
    public function shows(int $page = 0): Collection
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/shows', ['page' => $page]);

        $response->throw();

        return collect($response->json());
    }

    /**
     * Get all episodes for a show.
     *
     * @return array<int, array>
     */
    public function episodes(int $showId): array
    {
        $response = $this->client()
            ->get(self::BASE_URL."/shows/{$showId}/episodes", ['specials' => 1]);

        $response->throw();

        return $response->json();
    }

    /**
     * Get the full schedule of all future episodes globally.
     *
     * @return array<int, array>
     */
    public function fullSchedule(): array
    {
        $response = $this->client()
            ->timeout(60)
            ->get(self::BASE_URL.'/schedule/full');

        $response->throw();

        return $response->json();
    }

    /**
     * Get a single show by ID.
     *
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $response = $this->client()
            ->get(self::BASE_URL."/shows/{$id}");

        $response->throw();

        return $response->json();
    }

    /**
     * Get recently updated show IDs with their update timestamps.
     *
     * @param  string  $since  Time period: 'day', 'week', or 'month'
     * @return array<int, int> Map of show ID => Unix timestamp
     */
    public function showUpdates(string $since = 'day'): array
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/updates/shows', ['since' => $since]);

        $response->throw();

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->retry(3, 1000, when: fn ($e, $request) => $e->response?->status() === 429, throw: false);
    }
}
