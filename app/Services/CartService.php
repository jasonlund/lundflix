<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use App\Support\EpisodeCode;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * Get movie IDs in the cart.
     *
     * @return array<int, int>
     */
    public function movies(): array
    {
        return session(self::SESSION_KEY.'.movies', []);
    }

    /**
     * Get episode entries in the cart.
     *
     * @return array<int, array{show_id: int, code: string}>
     */
    public function episodes(): array
    {
        return session(self::SESSION_KEY.'.episodes', []);
    }

    /**
     * Toggle a movie in/out of the cart.
     *
     * @return bool True if added, false if removed
     */
    public function toggleMovie(int $movieId): bool
    {
        $movies = collect($this->movies());

        if ($movies->contains($movieId)) {
            session([self::SESSION_KEY.'.movies' => $movies->reject(fn ($id) => $id === $movieId)->values()->all()]);

            return false;
        }

        session([self::SESSION_KEY.'.movies' => $movies->push($movieId)->all()]);

        return true;
    }

    /**
     * Remove an item from cart.
     *
     * @param  int|Episode|array{show_id?: int, season?: int, number?: int, type?: string}  $item  Movie ID (int) or Episode/array
     */
    public function remove(int|Episode|array $item): void
    {
        if (is_int($item)) {
            session([self::SESSION_KEY.'.movies' => collect($this->movies())->reject(fn ($id) => $id === $item)->values()->all()]);
        } elseif ($item instanceof Episode || $this->isEpisodeArray($item)) {
            $entry = $this->makeEpisodeEntry($item);
            $episodes = $this->removeEpisodeEntry($this->episodes(), $entry);
            session([self::SESSION_KEY.'.episodes' => $episodes]);
        }
    }

    /**
     * Check if an item is in the cart.
     *
     * @param  int|Episode|array{show_id?: int, season?: int, number?: int, type?: string}  $item  Movie ID (int) or Episode/array
     */
    public function has(int|Episode|array $item): bool
    {
        if (is_int($item)) {
            return collect($this->movies())->contains($item);
        }

        if ($item instanceof Episode || $this->isEpisodeArray($item)) {
            $entry = $this->makeEpisodeEntry($item);

            return $this->hasEpisodeEntry($this->episodes(), $entry);
        }

        return false;
    }

    public function count(): int
    {
        return count($this->movies()) + count($this->episodes());
    }

    public function countEpisodesForShow(int $showId): int
    {
        return collect($this->episodes())
            ->filter(fn ($ep) => $ep['show_id'] === $showId)
            ->count();
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Sync episodes for a specific show. Replaces all episodes for the show.
     *
     * @param  array<int, string>  $episodeCodes  Format: S01E01 or s01e01
     */
    public function syncShowEpisodes(int $showId, array $episodeCodes): void
    {
        $otherShowEpisodes = collect($this->episodes())
            ->reject(fn ($ep) => $ep['show_id'] === $showId);

        $thisShowEpisodes = collect($episodeCodes)
            ->map(function ($code) use ($showId) {
                $parsed = EpisodeCode::parse(strtolower($code));

                return [
                    'show_id' => $showId,
                    'code' => EpisodeCode::generate($parsed['season'], $parsed['number'], $parsed['is_special']),
                ];
            })
            ->unique(fn ($ep) => $ep['code']);

        session([self::SESSION_KEY.'.episodes' => $otherShowEpisodes->concat($thisShowEpisodes)->values()->all()]);
    }

    /**
     * Load cart items as Eloquent models.
     *
     * @return Collection<int, Movie|Episode>
     */
    public function loadItems(): Collection
    {
        $movieIds = $this->movies();
        $episodeEntries = $this->episodes();

        $movies = Movie::whereIn('id', $movieIds)->get();

        $episodes = collect();
        if (! empty($episodeEntries)) {
            $episodes = Episode::with('show')
                ->where(function ($query) use ($episodeEntries) {
                    foreach ($episodeEntries as $ep) {
                        $parsed = EpisodeCode::parse($ep['code']);
                        $type = $parsed['is_special'] ? 'significant_special' : 'regular';
                        $query->orWhere(function ($q) use ($ep, $parsed, $type) {
                            $q->where('show_id', $ep['show_id'])
                                ->where('season', $parsed['season'])
                                ->where('number', $parsed['number'])
                                ->where('type', $type);
                        });
                    }
                })
                ->get();
        }

        return $movies->concat($episodes);
    }

    /**
     * Load and group cart items for consolidated display.
     *
     * @return array{movies: Collection<int, Movie>, shows: array<int, array{show: \App\Models\Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}
     */
    public function loadGroupedItems(): array
    {
        return app(RequestItemGrouper::class)->group($this->loadItems());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Check if the given array represents an episode.
     *
     * @param  array<string, mixed>  $item
     */
    private function isEpisodeArray(array $item): bool
    {
        return isset($item['show_id'], $item['season'], $item['number']);
    }

    /**
     * Create a cart entry for an episode.
     *
     * @param  Episode|array{show_id: int, season: int, number: int, type?: string}  $item
     * @return array{show_id: int, code: string}
     */
    private function makeEpisodeEntry(Episode|array $item): array
    {
        if ($item instanceof Episode) {
            return [
                'show_id' => $item->show_id,
                'code' => $item->code,
            ];
        }

        $isSpecial = ($item['type'] ?? 'regular') === 'significant_special';

        return [
            'show_id' => $item['show_id'],
            'code' => EpisodeCode::generate($item['season'], $item['number'], $isSpecial),
        ];
    }

    /**
     * Check if an episode entry exists in the cart.
     *
     * @param  array<int, array{show_id: int, code: string}>  $episodes
     * @param  array{show_id: int, code: string}  $entry
     */
    private function hasEpisodeEntry(array $episodes, array $entry): bool
    {
        foreach ($episodes as $ep) {
            if ($ep['show_id'] === $entry['show_id'] && $ep['code'] === $entry['code']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove an episode entry from the array.
     *
     * @param  array<int, array{show_id: int, code: string}>  $episodes
     * @param  array{show_id: int, code: string}  $entry
     * @return array<int, array{show_id: int, code: string}>
     */
    private function removeEpisodeEntry(array $episodes, array $entry): array
    {
        return array_values(array_filter($episodes, function ($ep) use ($entry) {
            return ! ($ep['show_id'] === $entry['show_id'] && $ep['code'] === $entry['code']);
        }));
    }
}
