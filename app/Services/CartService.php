<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EpisodeType;
use App\Enums\MovieStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Support\AirDateTime;
use App\Support\EpisodeCode;
use Illuminate\Support\Collection;

class CartService
{
    /**
     * Load cart items from arrays without reading from session.
     *
     * @param  array<int, int>  $movieIds
     * @param  array<int, array{show_id: int, code: string}>  $episodeEntries
     * @return Collection<int, Movie|Episode>
     */
    public function loadItemsFromIds(array $movieIds, array $episodeEntries): Collection
    {
        $movies = Movie::whereIn('id', $movieIds)
            ->where('status', MovieStatus::Released)
            ->get();

        $episodes = collect();
        if ($episodeEntries !== []) {
            $episodes = Episode::with('show')
                ->where(function ($query) use ($episodeEntries): void {
                    foreach ($episodeEntries as $ep) {
                        $parsed = EpisodeCode::parse($ep['code']);
                        $type = $parsed['is_special'] ? EpisodeType::SignificantSpecial : EpisodeType::Regular;
                        $query->orWhere(function ($q) use ($ep, $parsed, $type): void {
                            $q->where('show_id', $ep['show_id'])
                                ->where('season', $parsed['season'])
                                ->where('number', $parsed['number'])
                                ->where('type', $type);
                        });
                    }
                })
                ->get()
                ->filter(fn (Episode $episode): bool => $episode->airdate !== null && AirDateTime::hasAired(
                    $episode->airdate,
                    $episode->airtime,
                    $episode->show->web_channel, // @phpstan-ignore argument.type (casted to array)
                    $episode->show->network, // @phpstan-ignore argument.type (casted to array)
                ))
                ->values();
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
        $movies = $items->filter(fn ($item): bool => $item instanceof Movie)->values();
        $episodes = $items->filter(fn ($item): bool => $item instanceof Episode);

        return [
            'movies' => $movies,
            'shows' => $this->groupEpisodesByShow($episodes),
        ];
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

        usort($result, fn (array $a, array $b): int => strcmp((string) $a['show']->name, (string) $b['show']->name));

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

        usort($result, fn (array $a, array $b): int => $a['season'] <=> $b['season']);

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

        $sortedAll = $allSeasonEpisodes
            ->sort(fn ($a, $b): int => EpisodeCode::compareForSorting($a->toArray(), $b->toArray()))
            ->values();

        $cartIds = $cartEpisodes->pluck('id')->all();

        $runs = [];
        $currentRun = collect();

        foreach ($sortedAll as $episode) {
            $inCart = in_array($episode->id, $cartIds, true);

            if ($inCart) {
                $currentRun->push($episode);
            } elseif ($currentRun->isNotEmpty()) {
                $runs[] = $currentRun;
                $currentRun = collect();
            }
        }

        if ($currentRun->isNotEmpty()) {
            $runs[] = $currentRun;
        }

        return $runs;
    }
}
