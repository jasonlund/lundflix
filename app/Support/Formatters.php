<?php

namespace App\Support;

use App\Enums\ShowStatus;
use App\Models\Movie;
use App\Models\Show;

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
