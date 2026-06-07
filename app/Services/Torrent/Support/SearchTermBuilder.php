<?php

declare(strict_types=1);

namespace App\Services\Torrent\Support;

final class SearchTermBuilder
{
    public static function sanitize(string $name): string
    {
        $name = (string) preg_replace('/[\x{2010}-\x{2015}\x{2D}]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $name)));
    }

    /**
     * @return list<string>
     */
    public static function resolveTerms(mixed $stored, ?string $fallbackName): array
    {
        $terms = is_array($stored) ? array_values(array_filter(
            array_map(static fn ($t): string => is_string($t) ? trim($t) : '', $stored),
            static fn (string $t): bool => $t !== '',
        )) : [];

        if ($terms !== []) {
            return $terms;
        }

        $sanitized = self::sanitize((string) $fallbackName);

        return $sanitized === '' ? [] : [$sanitized];
    }

    /**
     * @param  list<string>  $terms
     */
    public static function buildOrQuery(array $terms): string
    {
        if (count($terms) === 1) {
            return $terms[0];
        }

        return implode('|', array_map(
            static fn (string $t): string => '"'.str_replace('"', '', $t).'"',
            $terms,
        ));
    }
}
