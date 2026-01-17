<?php

namespace App\Actions\Tv;

use App\Models\Episode;

class UpsertEpisodes
{
    /**
     * @param  array<int, array{tvmaze_id: int, show_id: int, season: int, number: ?int, name: string, type?: string, airdate?: ?string, rating?: mixed, image?: mixed, ...}>  $episodes
     */
    public function upsert(array $episodes): int
    {
        // Filter out insignificant specials
        $episodes = array_filter(
            $episodes,
            fn (array $ep): bool => ($ep['type'] ?? 'regular') !== 'insignificant_special'
        );

        // Assign sequential numbers to significant specials within each season
        $episodes = $this->assignSpecialNumbers($episodes);

        // Encode array fields for upsert (model casts don't apply)
        $data = array_map(fn ($ep) => [
            ...$ep,
            'rating' => is_array($ep['rating'] ?? null) ? json_encode($ep['rating']) : $ep['rating'],
            'image' => is_array($ep['image'] ?? null) ? json_encode($ep['image']) : $ep['image'],
        ], $episodes);

        if (empty($data)) {
            return 0;
        }

        return Episode::upsert(
            $data,
            ['tvmaze_id'],
            ['show_id', 'season', 'number', 'name', 'type', 'airdate', 'airtime', 'runtime', 'rating', 'image', 'summary']
        );
    }

    /**
     * Assign sequential numbers to significant specials within each season.
     * Numbers are assigned by airdate, with fallback to tvmaze_id.
     *
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    private function assignSpecialNumbers(array $episodes): array
    {
        // Separate regular episodes from significant specials
        $regular = [];
        $specials = [];

        foreach ($episodes as $ep) {
            if (($ep['type'] ?? 'regular') === 'significant_special') {
                $specials[] = $ep;
            } else {
                $regular[] = $ep;
            }
        }

        // Group specials by (show_id, season)
        $specialsByShowSeason = [];
        foreach ($specials as $special) {
            $key = $special['show_id'].'_'.$special['season'];
            $specialsByShowSeason[$key][] = $special;
        }

        // Sort each group by airdate (ascending), then by tvmaze_id as fallback
        foreach ($specialsByShowSeason as &$group) {
            usort($group, function (array $a, array $b): int {
                $aDate = $a['airdate'] ?? null;
                $bDate = $b['airdate'] ?? null;

                // Both have dates - compare them
                if ($aDate !== null && $bDate !== null) {
                    $cmp = strcmp($aDate, $bDate);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }
                // One has date, other doesn't - dated one comes first
                elseif ($aDate !== null) {
                    return -1;
                } elseif ($bDate !== null) {
                    return 1;
                }

                // Fallback to tvmaze_id
                return $a['tvmaze_id'] <=> $b['tvmaze_id'];
            });

            // Assign sequential numbers starting from 1
            foreach ($group as $i => &$special) {
                $special['number'] = $i + 1;
            }
        }

        // Flatten specials back into array
        $numberedSpecials = array_merge(...array_values($specialsByShowSeason));

        return array_merge($regular, $numberedSpecials);
    }
}
