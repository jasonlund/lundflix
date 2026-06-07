<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;

class Emoji
{
    /**
     * Convert a unicode emoji string to its codepoint filename stem.
     *
     * Example: "🤠" → "1f920"; "🇺🇸" → "1f1fa-1f1f8".
     *
     * Strips the U+FE0F variation selector (Apple PNG filenames omit it by
     * convention) but preserves ZWJ (U+200D) and skin-tone modifiers.
     */
    public static function codepoints(string $emoji): string
    {
        $codepoints = [];

        foreach (mb_str_split($emoji, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === 0xFE0F) {
                continue;
            }
            $codepoints[] = sprintf('%x', $cp);
        }

        return implode('-', $codepoints);
    }

    /**
     * Convert an ISO 3166-1 alpha-2 country code to the regional indicator
     * codepoint pair (e.g. "US" → "1f1fa-1f1f8").
     */
    public static function flagCodepoints(string $iso): string
    {
        $iso = strtoupper($iso);

        return collect(str_split($iso))
            ->map(fn (string $c): string => sprintf('%x', ord($c) - ord('A') + 0x1F1E6))
            ->join('-');
    }

    /**
     * Resolve the Vite-built URL for a codepoint filename, or null if the
     * asset file is not present on disk.
     */
    public static function assetUrl(string $codepoints): ?string
    {
        $relative = "resources/images/emoji/{$codepoints}.png";

        if (! file_exists(base_path($relative))) {
            return null;
        }

        return Vite::asset($relative);
    }
}
