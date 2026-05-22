<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Episode;
use Illuminate\Support\Collection;

class EpisodeGrouping
{
    /**
     * Group selected episodes by season with full-season detection and run finding.
     *
     * @param  Collection<int, Episode>  $selected  Episodes that are "in scope" (cart picks, matched Plex hits, etc.)
     * @param  Collection<int, Episode>  $allRegulars  All comparable episodes for the show (typically regulars + significant specials)
     * @return list<array{season: int, is_full: bool, runs: list<Collection<int, Episode>>, episodes?: Collection<int, Episode>}>
     */
    public static function groupBySeason(
        Collection $selected,
        Collection $allRegulars,
        bool $includeSelectedCollection = false,
    ): array {
        if ($selected->isEmpty()) {
            return [];
        }

        $bySeason = $selected->groupBy('season');
        $allBySeason = $allRegulars->groupBy('season');
        $result = [];

        foreach ($bySeason as $seasonNum => $seasonEpisodes) {
            $seasonRegulars = $allBySeason->get($seasonNum, collect());

            $entry = [
                'season' => (int) $seasonNum,
                'is_full' => self::isFullSeason($seasonEpisodes, $seasonRegulars),
                'runs' => self::findRuns($seasonEpisodes, $seasonRegulars),
            ];

            if ($includeSelectedCollection) {
                $entry['episodes'] = $seasonEpisodes;
            }

            $result[] = $entry;
        }

        usort($result, fn (array $a, array $b): int => $a['season'] <=> $b['season']);

        return $result;
    }

    /**
     * Check whether every episode in $allSeasonRegulars is also in $selected.
     *
     * @param  Collection<int, Episode>  $selected
     * @param  Collection<int, Episode>  $allSeasonRegulars
     */
    public static function isFullSeason(Collection $selected, Collection $allSeasonRegulars): bool
    {
        if ($allSeasonRegulars->isEmpty()) {
            return false;
        }

        $allIds = $allSeasonRegulars->pluck('id')->sort()->values();
        $selectedIds = $selected->pluck('id')->sort()->values();

        return $allIds->toArray() === $selectedIds->toArray();
    }

    /**
     * Find consecutive runs of selected episodes within a season, ordered by canonical airdate sort.
     *
     * @param  Collection<int, Episode>  $selected
     * @param  Collection<int, Episode>  $allSeasonRegulars
     * @return list<Collection<int, Episode>>
     */
    public static function findRuns(Collection $selected, Collection $allSeasonRegulars): array
    {
        if ($selected->isEmpty()) {
            return [];
        }

        $sortedAll = $allSeasonRegulars
            ->sort(fn ($a, $b): int => EpisodeCode::compareForSorting($a->toArray(), $b->toArray()))
            ->values();

        $selectedIds = $selected->pluck('id')->all();

        $runs = [];
        $currentRun = collect();

        foreach ($sortedAll as $episode) {
            if (in_array($episode->id, $selectedIds, true)) {
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
