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
            ->get(self::BASE_URL."/shows/{$showId}/episodes", ['specials' => 1]);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /**
     * Get the full schedule of all future episodes globally.
     * Returns null on failure.
     *
     * @return array<int, array>|null
     */
    public function fullSchedule(): ?array
    {
        $response = $this->client()
            ->timeout(60)
            ->get(self::BASE_URL.'/schedule/full');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get a single show by ID.
     * Returns null if the show doesn't exist (404).
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        $response = $this->client()
            ->get(self::BASE_URL."/shows/{$id}");

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /**
     * Get recently updated show IDs with their update timestamps.
     * Returns null on failure.
     *
     * @param  string  $since  Time period: 'day', 'week', or 'month'
     * @return array<int, int>|null Map of show ID => Unix timestamp
     */
    public function showUpdates(string $since = 'day'): ?array
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/updates/shows', ['since' => $since]);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->retry(3, 1000, when: fn ($e, $request) => $e->response?->status() === 429, throw: false);
    }
}
