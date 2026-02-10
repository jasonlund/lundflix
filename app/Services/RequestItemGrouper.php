<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Support\EpisodeCode;
use Illuminate\Support\Collection;

class RequestItemGrouper
{
    /**
     * Group cart items for consolidated display.
     *
     * @param  Collection<int, Movie|Episode>  $items
     * @return array{movies: Collection<int, Movie>, shows: array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}
     */
    public function group(Collection $items): array
    {
        $movies = $items->filter(fn ($item) => $item instanceof Movie)->values();
        $episodes = $items->filter(fn ($item) => $item instanceof Episode);

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
            ->where('type', '!=', 'insignificant_special')
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
}
