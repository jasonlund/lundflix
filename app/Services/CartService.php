<?php

namespace App\Services;

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
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
                        $type = $parsed['is_special'] ? EpisodeType::SignificantSpecial : EpisodeType::Regular;
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
     * @return array{movies: Collection<int, Movie>, shows: array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}
     */
    public function loadGroupedItems(): array
    {
        return $this->groupItems($this->loadItems());
    }

    /**
     * Load cart items from arrays without reading from session.
     *
     * @param  array<int, int>  $movieIds
     * @param  array<int, array{show_id: int, code: string}>  $episodeEntries
     * @return Collection<int, Movie|Episode>
     */
    public function loadItemsFromIds(array $movieIds, array $episodeEntries): Collection
    {
        $movies = Movie::whereIn('id', $movieIds)->get();

        $episodes = collect();
        if (! empty($episodeEntries)) {
            $episodes = Episode::with('show')
                ->where(function ($query) use ($episodeEntries) {
                    foreach ($episodeEntries as $ep) {
                        $parsed = EpisodeCode::parse($ep['code']);
                        $type = $parsed['is_special'] ? EpisodeType::SignificantSpecial : EpisodeType::Regular;
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
     * Load and group cart items from arrays without reading from session.
     *
     * @param  array<int, int>  $movieIds
     * @param  array<int, array{show_id: int, code: string}>  $episodeEntries
     * @return array{movies: Collection<int, Movie>, shows: array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}
     */
    public function loadGroupedItemsFromIds(array $movieIds, array $episodeEntries): array
    {
        return $this->groupItems($this->loadItemsFromIds($movieIds, $episodeEntries));
    }

    /**
     * Group items for consolidated display.
     *
     * @param  Collection<int, Movie|Episode>  $items
     * @return array{movies: Collection<int, Movie>, shows: array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}
     */
    public function groupItems(Collection $items): array
    {
        $movies = $items->filter(fn ($item) => $item instanceof Movie)->values();
        $episodes = $items->filter(fn ($item) => $item instanceof Episode);

        return [
            'movies' => $movies,
            'shows' => $this->groupEpisodesByShow($episodes),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Group episodes by show, then by season with run detection.
     *
     * @param  Collection<int, Episode>  $episodes
     * @return array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>
     */
    private function groupEpisodesByShow(Collection $episodes): array
    {
        if ($episodes->isEmpty()) {
            return [];
        }

        $byShow = $episodes->groupBy('show_id');
        $result = [];

        // Prefetch all episodes for all shows at once to avoid N+1 queries
        $showIds = $byShow->keys()->all();
        $allShowEpisodes = Episode::whereIn('show_id', $showIds)
            ->where('type', '!=', EpisodeType::InsignificantSpecial)
            ->get()
            ->groupBy('show_id');

        foreach ($byShow as $showId => $showEpisodes) {
            $show = $showEpisodes->first()->show;
            $showAllEpisodes = $allShowEpisodes->get($showId, collect());
            $seasons = $this->groupEpisodesBySeason($showEpisodes, $showAllEpisodes);

            $result[] = [
                'show' => $show,
                'seasons' => $seasons,
            ];
        }

        // Sort shows by name
        usort($result, fn ($a, $b) => strcmp($a['show']->name, $b['show']->name));

        return $result;
    }

    /**
     * Group episodes by season with full-season detection and run finding.
     *
     * @param  Collection<int, Episode>  $cartEpisodes  Episodes in the cart for this show
     * @param  Collection<int, Episode>  $allShowEpisodes  All episodes for this show (excluding insignificant specials)
     * @return array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>
     */
    private function groupEpisodesBySeason(Collection $cartEpisodes, Collection $allShowEpisodes): array
    {
        $bySeason = $cartEpisodes->groupBy('season');
        $allBySeason = $allShowEpisodes->groupBy('season');
        $result = [];

        foreach ($bySeason as $seasonNum => $seasonEpisodes) {
            $allSeasonEpisodes = $allBySeason->get($seasonNum, collect());

            $isFull = $this->isFullSeason($seasonEpisodes, $allSeasonEpisodes);
            $runs = $this->findRuns($seasonEpisodes, $allSeasonEpisodes);

            $result[] = [
                'season' => $seasonNum,
                'is_full' => $isFull,
                'runs' => $runs,
                'episodes' => $seasonEpisodes,
            ];
        }

        // Sort by season number
        usort($result, fn ($a, $b) => $a['season'] <=> $b['season']);

        return $result;
    }

    /**
     * Check if all episodes for a season are in the cart.
     *
     * @param  Collection<int, Episode>  $cartEpisodes
     * @param  Collection<int, Episode>  $allSeasonEpisodes
     */
    private function isFullSeason(Collection $cartEpisodes, Collection $allSeasonEpisodes): bool
    {
        if ($allSeasonEpisodes->isEmpty()) {
            return false;
        }

        $allIds = $allSeasonEpisodes->pluck('id')->sort()->values();
        $cartIds = $cartEpisodes->pluck('id')->sort()->values();

        return $allIds->toArray() === $cartIds->toArray();
    }

    /**
     * Find consecutive runs of episodes based on airdate order.
     *
     * @param  Collection<int, Episode>  $cartEpisodes
     * @param  Collection<int, Episode>  $allSeasonEpisodes
     * @return array<int, Collection<int, Episode>>
     */
    private function findRuns(Collection $cartEpisodes, Collection $allSeasonEpisodes): array
    {
        if ($cartEpisodes->isEmpty()) {
            return [];
        }

        // Sort ALL episodes by airdate using EpisodeCode::compareForSorting
        $sortedAll = $allSeasonEpisodes
            ->sort(fn ($a, $b) => EpisodeCode::compareForSorting($a->toArray(), $b->toArray()))
            ->values();

        // Get cart episode IDs for lookup
        $cartIds = $cartEpisodes->pluck('id')->all();

        // Build runs by walking through sorted full list
        $runs = [];
        $currentRun = collect();

        foreach ($sortedAll as $episode) {
            $inCart = in_array($episode->id, $cartIds, true);

            if ($inCart) {
                $currentRun->push($episode);
            } elseif ($currentRun->isNotEmpty()) {
                // Gap found - end current run
                $runs[] = $currentRun;
                $currentRun = collect();
            }
        }

        // Don't forget the last run
        if ($currentRun->isNotEmpty()) {
            $runs[] = $currentRun;
        }

        return $runs;
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

        $isSpecial = ($item['type'] ?? 'regular') === EpisodeType::SignificantSpecial->value;

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
}
