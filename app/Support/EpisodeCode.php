<?php

namespace App\Support;

use InvalidArgumentException;

class EpisodeCode
{
    /**
     * Generate episode code from season and number.
     *
     * @return string Code in format s01e05 (regular) or s01s05 (special)
     */
    public static function generate(int $season, int $number, bool $isSpecial = false): string
    {
        $prefix = $isSpecial ? 's' : 'e';

        return sprintf('s%02d%s%02d', $season, $prefix, $number);
    }

    /**
     * Parse episode code into components.
     *
     * @return array{season: int, number: int, is_special: bool}
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $code): array
    {
        if (! preg_match('/^s(\d+)(e|s)(\d+)$/i', $code, $matches)) {
            throw new InvalidArgumentException("Invalid episode code format: {$code}");
        }

        return [
            'season' => (int) $matches[1],
            'number' => (int) $matches[3],
            'is_special' => strtolower($matches[2]) === 's',
        ];
    }

    /**
     * Compare two episodes for sorting specials by airdate, then tvmaze_id.
     * Episodes with airdates sort before those without.
     *
     * @param  array{airdate?: ?string, tvmaze_id?: int, id?: int}  $a
     * @param  array{airdate?: ?string, tvmaze_id?: int, id?: int}  $b
     */
    public static function compareForSorting(array $a, array $b): int
    {
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

        // Fallback to tvmaze_id (or id for DB episodes)
        $aId = $a['tvmaze_id'] ?? $a['id'] ?? 0;
        $bId = $b['tvmaze_id'] ?? $b['id'] ?? 0;

        return $aId <=> $bId;
    }
}
