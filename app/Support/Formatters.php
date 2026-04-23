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

        if ($hours > 0 && $mins > 0) {
            return "{$prefix}{$hours}h{$mins}m";
        }

        if ($hours > 0) {
            return "{$prefix}{$hours}h";
        }

        return "{$prefix}{$mins}m";
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
     * Compact relative time string using the highest-order unit.
     *
     * Sub-hour gaps are floored to "1h" — this is intentional so the UI
     * never shows "0h" for very recent/imminent events.
     */
    private static function compactDiff(Carbon $target): string
    {
        $now = now();

        $hours = (int) $now->diffInHours($target, absolute: true);

        if ($hours < 24) {
            return max(1, $hours).'h';
        }

        $days = (int) $now->diffInDays($target, absolute: true);

        if ($days < 7) {
            return $days.'d';
        }

        if ($days < 30) {
            return ((int) floor($days / 7)).'w';
        }

        return ((int) floor($days / 30)).'m';
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
