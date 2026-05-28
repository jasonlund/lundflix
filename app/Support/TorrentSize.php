<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class TorrentSize
{
    private const UNITS = [
        'B' => 0,
        'K' => 1,
        'KB' => 1,
        'M' => 2,
        'MB' => 2,
        'G' => 3,
        'GB' => 3,
        'T' => 4,
        'TB' => 4,
    ];

    public static function parse(string $human): int
    {
        $trimmed = trim($human);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Cannot parse empty size string.');
        }

        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([A-Za-z]+)$/', $trimmed, $matches)) {
            throw new InvalidArgumentException(sprintf('Unparseable size string: "%s".', $human));
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2]);

        if (! array_key_exists($unit, self::UNITS)) {
            throw new InvalidArgumentException(sprintf('Unknown size unit "%s" in "%s".', $matches[2], $human));
        }

        $bytes = $value * (1024 ** self::UNITS[$unit]);

        return (int) round($bytes);
    }

    public static function format(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exponent = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $exponent < count($units) - 1) {
            $value /= 1024;
            $exponent++;
        }

        if ($exponent === 0) {
            return $bytes.' B';
        }

        return sprintf('%.2f %s', $value, $units[$exponent]);
    }
}
