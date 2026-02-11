<?php

namespace App\Support;

use App\Models\Episode;
use Illuminate\Support\Collection;

class RequestItemFormatter
{
    /**
     * Format a run of episodes for display.
     *
     * Single: "S01E05" or "S01S01"
     * Run: "S01E01-E05" (regular to regular)
     *      "S01E07-S01" (regular to special)
     *      "S01S01-S03" (special to special)
     *
     * @param  Collection<int, Episode>|array<int, Episode>  $episodes
     */
    public static function formatRun(Collection|array $episodes): string
    {
        $episodes = $episodes instanceof Collection ? $episodes->values()->all() : array_values($episodes);

        if (count($episodes) === 0) {
            return '';
        }

        if (count($episodes) === 1) {
            return strtoupper($episodes[0]->code);
        }

        $start = $episodes[0];
        $end = $episodes[count($episodes) - 1];

        $startCode = strtoupper($start->code);
        $endSuffix = ($end->isSpecial() ? 'S' : 'E').sprintf('%02d', $end->number);

        return $startCode.'-'.$endSuffix;
    }

    /**
     * Format a full season label.
     */
    public static function formatSeason(int $season): string
    {
        return sprintf('S%02d', $season);
    }
}
