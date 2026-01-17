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
}
