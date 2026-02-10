<?php

namespace App\Actions\Tv;

use App\Support\EpisodeCode;

class PrepareEpisodesForDisplay
{
    /**
     * Filter out insignificant specials and assign sequential numbers to significant specials.
     *
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    public function prepare(array $episodes): array
    {
        $filtered = $this->filterInsignificantSpecials($episodes);

        return $this->assignSpecialNumbers($filtered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    private function filterInsignificantSpecials(array $episodes): array
    {
        return array_values(array_filter(
            $episodes,
            fn (array $ep): bool => ($ep['type'] ?? 'regular') !== 'insignificant_special'
        ));
    }

    /**
     * Assign sequential numbers to significant specials within each season.
     * Numbers are assigned by airdate, with fallback to tvmaze_id/id.
     *
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    private function assignSpecialNumbers(array $episodes): array
    {
        $regular = [];
        $specials = [];

        foreach ($episodes as $ep) {
            if (($ep['type'] ?? 'regular') === 'significant_special') {
                $specials[] = $ep;
            } else {
                $regular[] = $ep;
            }
        }

        $specialsBySeason = [];
        foreach ($specials as $special) {
            $key = ($special['show_id'] ?? 'all').'_'.$special['season'];
            $specialsBySeason[$key][] = $special;
        }

        if (empty($specialsBySeason)) {
            return $regular;
        }

        foreach ($specialsBySeason as &$group) {
            usort($group, EpisodeCode::compareForSorting(...));

            foreach ($group as $i => &$special) {
                $special['number'] = $i + 1;
            }
        }

        $numberedSpecials = array_merge(...array_values($specialsBySeason));

        return array_merge($regular, $numberedSpecials);
    }
}
