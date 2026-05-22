<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ShowStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Formatters
{
    public static function runtime(?int $minutes, bool $approximate = false): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $prefix = $approximate ? '~' : '';
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return "{$prefix}{$hours}h{$mins}m";
    }

    public static function runtimeFor(Show|Movie $item): ?string
    {
        if ($item instanceof Show) {
            $runtime = $item->displayRuntime();

            return $runtime ? self::runtime($runtime['value'], $runtime['approximate']) : null;
        }

        return self::runtime($item->runtime);
    }

    public static function compactYearLabel(Show|Movie $item): ?string
    {
        if ($item instanceof Movie) {
            return $item->year ? self::shortYear($item->year) : null;
        }

        if (! $item->premiered) {
            return null;
        }

        $start = self::shortYear($item->premiered->year); // @phpstan-ignore property.nonObject (casted to Carbon)

        if ($item->ended) {
            return $start.'-'.self::shortYear($item->ended->year); // @phpstan-ignore property.nonObject (casted to Carbon)
        }

        if ($item->status === ShowStatus::Running) { // @phpstan-ignore identical.alwaysFalse (casted to ShowStatus)
            return $start.'-';
        }

        return $start;
    }

    private static function shortYear(int $year): string
    {
        return "'".substr((string) $year, -2);
    }

    /**
     * Format a run of episodes for display.
     *
     * Single: "S01E05" or "S01S01"
     * Run: "S01E01-E05" (regular to regular)
     *      "S01E07-S01" (regular to special)
     *      "S01S01-S03" (special to special)
     *
     * Grammar: each segment is `S##` (season prefix, always two digits) followed by either
     * `E##` (regular episode) or `S##` (special). The fixed-width `S##` prefix means
     * `S01S01-S02E03` parses unambiguously as season 01 / special 01 → season 02 / episode 03.
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
            return strtoupper((string) $episodes[0]->code);
        }

        $start = $episodes[0];
        $end = $episodes[count($episodes) - 1];

        $startCode = strtoupper((string) $start->code);

        if ($end->isSpecial() || $end->season !== $start->season) {
            $endSuffix = sprintf('S%02d', $end->season).($end->isSpecial() ? 'S' : 'E').sprintf('%02d', $end->number);
        } else {
            $endSuffix = 'E'.sprintf('%02d', $end->number);
        }

        return $startCode.'-'.$endSuffix;
    }

    /**
     * Format a full season label.
     */
    public static function formatSeason(int $season): string
    {
        return sprintf('S%02d', $season);
    }

    /**
     * Format a grouped seasons/runs structure into a list of labels for inline display.
     *
     * @param  array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>}>  $seasons
     * @return list<string>
     */
    public static function seasonRunLabels(array $seasons): array
    {
        $labels = [];

        foreach ($seasons as $seasonData) {
            if ($seasonData['is_full']) {
                $labels[] = self::formatSeason($seasonData['season']);

                continue;
            }

            foreach ($seasonData['runs'] as $run) {
                $labels[] = self::formatRun($run);
            }
        }

        return $labels;
    }

    public static function formatResolution(?string $resolution): ?string
    {
        if ($resolution === null) {
            return null;
        }

        return match (strtolower($resolution)) {
            '4k' => '4K',
            'sd' => 'SD',
            default => $resolution.'p',
        };
    }

    /**
     * Format a date as `n/j` for the current year or `n/j/y` otherwise.
     */
    public static function shortDate(Carbon $date): string
    {
        $format = $date->year === now(UserTime::timezone())->year ? 'n/j' : 'n/j/y';

        return $date->format($format);
    }

    /**
     * Format a past date as a compact relative string. Delegates to compactDiff().
     */
    public static function timeSince(Carbon $target): string
    {
        return self::compactDiff($target);
    }

    /**
     * Format a future date as a compact relative string. Delegates to compactDiff().
     */
    public static function timeUntil(Carbon $target): string
    {
        return self::compactDiff($target);
    }

    /**
     * Compact relative time with direction: bare for future, parenthesized for past.
     */
    public static function relativeTime(Carbon $target): string
    {
        $diff = self::compactDiff($target);

        return $target->isPast() ? "({$diff})" : $diff;
    }

    /**
     * Compact relative time string using the highest-order unit.
     *
     * Boundaries roll over to the next unit: 60 minutes becomes "1h", 48 hours becomes "2d",
     * 7 days becomes "1w", and 30 days becomes "1mo". Thus "60m", "48h", "7d", and "30d"
     * are intentionally never returned.
     */
    private static function compactDiff(Carbon $target): string
    {
        $now = now();

        $minutes = (int) $now->diffInMinutes($target, absolute: true);

        if ($minutes < 60) {
            return max(1, $minutes).'m';
        }

        $hours = (int) $now->diffInHours($target, absolute: true);

        if ($hours < 48) {
            return $hours.'h';
        }

        $days = (int) $now->diffInDays($target, absolute: true);

        if ($days < 7) {
            return $days.'d';
        }

        if ($days < 30) {
            return ((int) floor($days / 7)).'w';
        }

        return ((int) floor($days / 30)).'mo';
    }

    public static function yearLabel(Show|Movie $item): ?string
    {
        if ($item instanceof Movie) {
            return $item->year ? (string) $item->year : null;
        }

        if (! $item->premiered) {
            return null;
        }

        $startYear = $item->premiered->year; // @phpstan-ignore property.nonObject (casted to Carbon)

        if ($item->ended) {
            return $startYear.'-'.$item->ended->year; // @phpstan-ignore property.nonObject (casted to Carbon)
        }

        if ($item->status === ShowStatus::Running) { // @phpstan-ignore identical.alwaysFalse (casted to ShowStatus)
            return $startYear.'-present';
        }

        return (string) $startYear;
    }
}
