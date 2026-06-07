<?php

declare(strict_types=1);

namespace App\Services\Torrent\Support;

final class PackNameParser
{
    /**
     * Parse torrent name for season tokens.
     *
     * @return array{type: 'single'|'range'|'complete', start: int|null, end: int|null}|null
     */
    public static function parse(string $name): ?array
    {
        if (preg_match('/(?:^|[\s.\-_])S(\d{1,2})[\s.\-_]*(?:[-~\x{2013}\x{2014}][\s.\-_]*)?S(\d{1,2})\b/iu', $name, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];

            if ($end < $start) {
                return null;
            }

            if ($end === $start) {
                return ['type' => 'single', 'start' => $start, 'end' => $start];
            }

            return ['type' => 'range', 'start' => $start, 'end' => $end];
        }

        if (preg_match('/Seasons?\s*(\d{1,2})[\s.\-_]*(?:[-~\x{2013}\x{2014}]|to)[\s.\-_]*(\d{1,2})/iu', $name, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];

            if ($end < $start) {
                return null;
            }

            if ($end === $start) {
                return ['type' => 'single', 'start' => $start, 'end' => $start];
            }

            return ['type' => 'range', 'start' => $start, 'end' => $end];
        }

        if (preg_match('/\bComplete[\s.\-_]+(?:Series|Collection|Seasons?)\b/iu', $name)) {
            return ['type' => 'complete', 'start' => null, 'end' => null];
        }

        if (preg_match('/(?:^|[\s.\-_])S(\d{1,2})(?:[\s.\-_]|$)/iu', $name, $m)) {
            $season = (int) $m[1];

            return ['type' => 'single', 'start' => $season, 'end' => $season];
        }

        if (preg_match('/\bSeason[\s.\-_]*(\d{1,2})\b/iu', $name, $m)) {
            $season = (int) $m[1];

            return ['type' => 'single', 'start' => $season, 'end' => $season];
        }

        return null;
    }
}
